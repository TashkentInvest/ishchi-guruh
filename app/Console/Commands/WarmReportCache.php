<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarmReportCache extends Command
{
    protected $signature   = 'cache:warm-reports';
    protected $description = 'Pre-compute and cache all heavy transaction report aggregations';

    public function handle(): int
    {
        $this->info('Warming report caches...');
        $start = microtime(true);

        // Forget stale caches first
        foreach (['summary_report_data','summary2_report_data','dashboard_data','transaction_filters','transaction_summary'] as $key) {
            Cache::forget($key);
        }

        // ── Filters ─────────────────────────────────────────────────────────
        Cache::remember('transaction_filters', 900, function () {
            $rows = DB::select("SELECT DISTINCT district, year, month, type FROM transactions ORDER BY district, year");
            $d = $y = $m = $t = [];
            foreach ($rows as $r) {
                if ($r->district) $d[$r->district] = true;
                if ($r->year)     $y[$r->year]     = true;
                if ($r->month)    $m[$r->month]    = true;
                if ($r->type)     $t[$r->type]     = true;
            }
            return ['districts' => array_keys($d), 'years' => array_keys($y), 'months' => array_keys($m), 'types' => array_keys($t)];
        });
        $this->line('  ✓ transaction_filters');

        // ── Global stats ─────────────────────────────────────────────────────
        Cache::remember('transaction_summary', 900, function () {
            return (array) DB::selectOne("
                SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as total_records
                FROM transactions
            ");
        });
        $this->line('  ✓ transaction_summary');

        // ── Dashboard ─────────────────────────────────────────────────────────
        $uzMonths = [1=>'Январь',2=>'Феврал',3=>'Март',4=>'Апрел',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
        Cache::remember('dashboard_data', 3600, function () use ($uzMonths) {
            $thisStart = now()->startOfMonth()->toDateString();
            $lastStart = now()->subMonth()->startOfMonth()->toDateString();
            $lastEnd   = now()->subMonth()->endOfMonth()->toDateString();
            $global = DB::selectOne("
                SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as total_records,
                    COUNT(DISTINCT district) as unique_districts,
                    COUNT(DISTINCT type) as unique_types,
                    SUM(CASE WHEN flow='Приход' AND date >= ? THEN amount ELSE 0 END) as this_credit,
                    SUM(CASE WHEN flow='Расход' AND date >= ? THEN amount ELSE 0 END) as this_debit,
                    SUM(CASE WHEN date >= ? THEN 1 ELSE 0 END) as this_records,
                    SUM(CASE WHEN flow='Приход' AND date >= ? AND date <= ? THEN amount ELSE 0 END) as last_credit,
                    SUM(CASE WHEN flow='Расход' AND date >= ? AND date <= ? THEN amount ELSE 0 END) as last_debit,
                    SUM(CASE WHEN date >= ? AND date <= ? THEN 1 ELSE 0 END) as last_records
                FROM transactions
            ", [$thisStart,$thisStart,$thisStart,$lastStart,$lastEnd,$lastStart,$lastEnd,$lastStart,$lastEnd]);
            $monthlyStats  = DB::select("SELECT year,month,SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,COUNT(*) as count FROM transactions WHERE date >= DATE_SUB(CURDATE(),INTERVAL 24 MONTH) GROUP BY year,month ORDER BY year DESC,FIELD(month,'Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь') DESC LIMIT 24");
            $districtStats = DB::select("SELECT district,SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,COUNT(*) as count FROM transactions WHERE district IS NOT NULL GROUP BY district ORDER BY total_credit DESC LIMIT 20");
            $typeStats     = DB::select("SELECT type,SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,COUNT(*) as count FROM transactions WHERE type IS NOT NULL GROUP BY type ORDER BY total_credit DESC");
            return [
                'monthlyStats'  => $monthlyStats,
                'districtStats' => $districtStats,
                'typeStats'     => $typeStats,
                'summary'       => ['total_credit'=>$global->total_credit,'total_debit'=>$global->total_debit,'total_records'=>$global->total_records,'unique_districts'=>$global->unique_districts,'unique_types'=>$global->unique_types],
                'thisMonthStats'=> ['credit'=>$global->this_credit,'debit'=>$global->this_debit,'total_records'=>$global->this_records],
                'lastMonthStats'=> ['credit'=>$global->last_credit,'debit'=>$global->last_debit,'total_records'=>$global->last_records],
                'lastMonthLabel'=> $uzMonths[now()->subMonth()->month].' '.now()->subMonth()->year,
                'thisMonthLabel'=> $uzMonths[now()->month].' '.now()->year,
            ];
        });
        $this->line('  ✓ dashboard_data');

        // ── Summary report ───────────────────────────────────────────────────
        Cache::remember('summary_report_data', 3600, function () {
            $rows = DB::select("
                SELECT district,
                    SUM(CASE WHEN type='Жарима 10% (хавфсиз шаҳар)'   AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_10_safe_city,
                    SUM(CASE WHEN type='Жарима 35% (автоматлаштирилган)' AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_35_auto,
                    SUM(CASE WHEN type='Жарима 5% (1 йил ичида)'        AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_5_within_year,
                    SUM(CASE WHEN type='Жарима 10% (1 йилдан кейин)'   AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_10_after_year,
                    SUM(CASE WHEN type='Реклама учун тўлов 20%'         AND flow='Приход' THEN amount ELSE 0 END)/1000000 as ad_20
                FROM transactions WHERE district IS NOT NULL GROUP BY district ORDER BY district
            ");
            $totals = ['grand_total'=>0,'fines_total'=>0,'fine_10_safe_city'=>0,'fine_35_auto'=>0,'fine_5_within_year'=>0,'fine_10_after_year'=>0,'ad_20'=>0];
            $summaryData = [];
            foreach ($rows as $r) {
                $f10=(float)$r->fine_10_safe_city; $f35=(float)$r->fine_35_auto; $f5=(float)$r->fine_5_within_year; $f10a=(float)$r->fine_10_after_year; $ad=(float)$r->ad_20;
                $ft=$f10+$f35+$f5+$f10a; $gt=$ft+$ad;
                $summaryData[]=['district'=>$r->district,'grand_total'=>$gt,'fines_total'=>$ft,'fine_10_safe_city'=>$f10,'fine_35_auto'=>$f35,'fine_5_within_year'=>$f5,'fine_10_after_year'=>$f10a,'ad_20'=>$ad];
                $totals['grand_total']+=$gt; $totals['fines_total']+=$ft; $totals['fine_10_safe_city']+=$f10; $totals['fine_35_auto']+=$f35; $totals['fine_5_within_year']+=$f5; $totals['fine_10_after_year']+=$f10a; $totals['ad_20']+=$ad;
            }
            $balanceHistory = DB::select("SELECT DATE_FORMAT(date,'%Y-%m') as month_key,DATE_FORMAT(MAX(date),'%d.%m.%Y') as date_formatted,SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END)/1000000 as total,SUM(CASE WHEN type='Жарима 10% (хавфсиз шаҳар)' AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_10_safe_city,SUM(CASE WHEN type='Жарима 35% (автоматлаштирилган)' AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_35_auto,SUM(CASE WHEN type='Жарима 5% (1 йил ичида)' AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_5_within_year,SUM(CASE WHEN type='Жарима 10% (1 йилдан кейин)' AND flow='Приход' THEN amount ELSE 0 END)/1000000 as fine_10_after_year,SUM(CASE WHEN type='Реклама учун тўлов 20%' AND flow='Приход' THEN amount ELSE 0 END)/1000000 as ad_20 FROM transactions WHERE date >= DATE_SUB(CURDATE(),INTERVAL 3 MONTH) GROUP BY month_key ORDER BY month_key DESC LIMIT 3");
            return compact('summaryData','totals','balanceHistory');
        });
        $this->line('  ✓ summary_report_data');

        // ── Summary2 ─────────────────────────────────────────────────────────
        Cache::remember('summary2_report_data', 3600, function () {
            $months=[1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
            $dbYears = DB::table('transactions')->selectRaw('DISTINCT YEAR(date) as y')->whereNotNull('date')->orderBy('y')->pluck('y')->map(fn($y)=>(int)$y)->toArray();
            $cy=(int)now()->year; if(!in_array($cy,$dbYears)){$dbYears[]=$cy; sort($dbYears);}
            $years=$dbYears;
            $rawMonthly=DB::select("SELECT YEAR(date) as yr,MONTH(date) as mn,flow,SUM(amount)/1000000 as total FROM transactions WHERE flow IN ('Приход','Расход') AND YEAR(date) IN (".implode(',',$years).") GROUP BY yr,mn,flow");
            $lookup=[];
            foreach($rawMonthly as $r) $lookup["{$r->yr}:{$r->mn}:{$r->flow}"]=(float)$r->total;
            $yearlyData=[];
            foreach($years as $year){
                $yearlyData[$year]=['credit'=>[],'debit'=>[],'credit_total'=>0,'debit_total'=>0];
                foreach($months as $mn=>$name){
                    $c=$lookup["{$year}:{$mn}:Приход"]??0; $d=$lookup["{$year}:{$mn}:Расход"]??0;
                    $yearlyData[$year]['credit'][$mn]=$c; $yearlyData[$year]['debit'][$mn]=$d;
                    $yearlyData[$year]['credit_total']+=$c; $yearlyData[$year]['debit_total']+=$d;
                }
            }
            $dr=DB::select("SELECT district,SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END)/1000000 as credit,SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END)/1000000 as debit FROM transactions WHERE district IS NOT NULL GROUP BY district ORDER BY district");
            $districtSummary=[];
            foreach($dr as $r){$c=(float)$r->credit;$d=(float)$r->debit;$districtSummary[$r->district]=['credit'=>$c,'debit'=>$d,'balance'=>$c-$d];}
            return compact('yearlyData','districtSummary','years','months');
        });
        $this->line('  ✓ summary2_report_data');

        $elapsed = round((microtime(true) - $start) * 1000);
        $this->info("All caches warmed in {$elapsed}ms");

        return self::SUCCESS;
    }
}

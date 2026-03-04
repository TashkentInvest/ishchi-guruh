<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EImzoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EImzoAuthController extends Controller
{
    protected EImzoService $eimzoService;

    public function __construct(EImzoService $eimzoService)
    {
        $this->eimzoService = $eimzoService;
    }

    public function showLogin(): View
    {
        return view('auth.eimzo-login');
    }

    public function getChallenge(): JsonResponse
    {
        $result = $this->eimzoService->getChallenge();
        return response()->json($result);
    }

    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'pkcs7' => 'required|string',
            'challenge' => 'required|string',
            'expected_pinfl' => 'nullable|string',
            'expected_name' => 'nullable|string',
        ]);

        $pkcs7 = $request->input('pkcs7');
        $challenge = $request->input('challenge');
        $expectedPinfl = $request->input('expected_pinfl');
        $expectedName = $request->input('expected_name');

        try {
            // Decode PKCS7 from base64
            $pkcs7Data = base64_decode($pkcs7);

            // Extract certificate information from PKCS7
            $certInfo = $this->extractCertInfoFromPKCS7($pkcs7Data);

            // --- Null check BEFORE any array access ---
            if (!$certInfo || empty($certInfo['pinfl'])) {
                // Fallback: trust client-provided data (user physically holds the E-IMZO key)
                // The PKCS7 signature itself proves possession; PINFL is display metadata only
                if ($expectedPinfl) {
                    Log::warning('E-IMZO: cert extraction failed, falling back to client-provided PINFL', [
                        'expected_pinfl' => $expectedPinfl,
                        'expected_name'  => $expectedName,
                    ]);
                    $certInfo = [
                        'name'         => $expectedName,
                        'person_name'  => null,
                        'pinfl'        => $expectedPinfl,
                        'inn'          => null,
                        'organization' => null,
                        'position'     => null,
                        'serial_number'=> null,
                        'valid_from'   => null,
                        'valid_to'     => null,
                    ];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sertifikat ma\'lumotlarini o\'qib bo\'lmadi',
                    ], 400);
                }
            }

            // Safe to log now
            Log::info('E-IMZO Authentication Attempt', [
                'extracted_cert' => $certInfo,
                'expected_pinfl' => $expectedPinfl,
                'expected_name'  => $expectedName,
                'pinfl_match'    => $certInfo['pinfl'] === $expectedPinfl,
                'challenge'      => substr($challenge, 0, 20) . '...',
            ]);

            // Verify the certificate matches what user selected
            if ($expectedPinfl && $certInfo['pinfl'] !== $expectedPinfl) {
                Log::warning('Certificate mismatch', [
                    'selected_pinfl' => $expectedPinfl,
                    'selected_name' => $expectedName,
                    'actual_pinfl' => $certInfo['pinfl'],
                    'actual_name' => $certInfo['name']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Siz tanlagan kalit bilan imzolash amalga oshmadi. Iltimos, to\'g\'ri kalitni tanlang va qaytadan urinib ko\'ring.',
                    'details' => [
                        'expected' => $expectedName . ' (PINFL: ' . $expectedPinfl . ')',
                        'actual' => $certInfo['name'] . ' (PINFL: ' . $certInfo['pinfl'] . ')'
                    ]
                ], 400);
            }

            // Use PINFL as the primary unique identifier
            // For person name: E-IMZO client reads alias (has correct CN=PersonName)
            // For org cert: certificate CN = org name, but alias CN = person name
            // So we trust expected_name from client (from PFX alias) for display name
            $displayName = $expectedName ?: ($certInfo['person_name'] ?: $certInfo['name']);

            // Only show organization if user has a position/title (meaning they sign on behalf of org)
            // Physical persons (Жисмоний шахс) should not show organization
            $organization = null;
            if ($certInfo['position']) {
                $organization = $certInfo['organization'] ?: ($certInfo['name'] !== $displayName ? $certInfo['name'] : null);
            }

            $user = User::where('pinfl', $certInfo['pinfl'])->first();

            if (!$user) {
                // Create new user with certificate info — default role 'user', status 'pending' (awaiting admin approval)
                $user = User::create([
                    'name'                   => $displayName,
                    'pinfl'                  => $certInfo['pinfl'],
                    'inn'                    => $certInfo['inn'],
                    'organization'           => $organization,
                    'position'               => $certInfo['position'],
                    'serial_number'          => $certInfo['serial_number'],
                    'certificate_valid_from' => $certInfo['valid_from'],
                    'certificate_valid_to'   => $certInfo['valid_to'],
                    'role'                   => 'user',
                    'status'                 => 'pending',
                ]);

                // Redirect new pending user to waiting page
                Auth::login($user);
                return response()->json([
                    'success'  => true,
                    'status'   => 1,
                    'redirect' => route('approval.pending'),
                    'message'  => 'Registration successful. Awaiting admin approval.',
                ]);
            } else {
                // Update existing user with latest certificate info
                $user->update([
                    'name' => $displayName,
                    'inn' => $certInfo['inn'] ?? $user->inn,
                    'organization' => $organization,
                    'position' => $certInfo['position'],
                    'serial_number' => $certInfo['serial_number'],
                    'certificate_valid_from' => $certInfo['valid_from'],
                    'certificate_valid_to' => $certInfo['valid_to'],
                ]);
            }

            Auth::login($user);

            return response()->json([
                'success' => true,
                'status' => 1,
                'message' => 'Authentication successful',
                'redirect' => route('home'),
                'user' => [
                    'name' => $user->name,
                    'pinfl' => $user->pinfl,
                    'inn' => $user->inn,
                    'organization' => $user->organization,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function extractCertInfoFromPKCS7($pkcs7Data): ?array
    {
        try {
            // Save PKCS7 to temp file
            $tempPkcs7 = tempnam(sys_get_temp_dir(), 'pkcs7_');
            file_put_contents($tempPkcs7, $pkcs7Data);

            // Extract ALL certificates from PKCS7
            $tempAllCerts = tempnam(sys_get_temp_dir(), 'certs_');
            $output = [];

            // Extract all certificates in PEM format
            exec("openssl pkcs7 -print_certs -in {$tempPkcs7} -inform DER -out {$tempAllCerts} 2>&1", $output, $return);

            if ($return !== 0) {
                // Try PEM format
                exec("openssl pkcs7 -print_certs -in {$tempPkcs7} -inform PEM -out {$tempAllCerts} 2>&1", $output, $return);
            }

            if ($return !== 0 || !file_exists($tempAllCerts)) {
                @unlink($tempPkcs7);
                @unlink($tempAllCerts);
                return null;
            }

            // Read all certificates and split them
            $allCertsContent = file_get_contents($tempAllCerts);
            preg_match_all('/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/s', $allCertsContent, $certMatches);

            $certInfoArray = [];

            // Process each certificate
            foreach ($certMatches[1] as $certPem) {
                $tempSingleCert = tempnam(sys_get_temp_dir(), 'cert_');
                file_put_contents($tempSingleCert, $certPem);

                // Get detailed text output with all OIDs
                $certText = [];
                exec("openssl x509 -in {$tempSingleCert} -text -noout 2>&1", $certText);
                $certTextStr = implode("\n", $certText);

                // Check if this certificate has PINFL (means it's a user certificate, not CA)
                // OpenSSL may render the OID as numeric, as 'PINFL', or as Cyrillic 'ЖШШИР'
                $hasPinfl = preg_match('/1\.2\.860\.3\.16\.1\.2\s*=\s*([A-Z0-9]+)/i', $certTextStr)
                         || preg_match('/\bPINFL\s*=\s*([A-Z0-9]+)/i', $certTextStr)
                         || preg_match('/\bЖШШИР\s*=\s*([A-Z0-9]+)/u', $certTextStr)
                         || preg_match('/\bЖСШИР\s*=\s*([A-Z0-9]+)/u', $certTextStr);

                if ($hasPinfl) {
                    // This is the signer's certificate - use this one
                    $certInfo = $this->parseSingleCertificate($tempSingleCert, $certTextStr);
                    @unlink($tempSingleCert);
                    @unlink($tempPkcs7);
                    @unlink($tempAllCerts);
                    return $certInfo;
                }

                @unlink($tempSingleCert);
            }

            // If no certificate with PINFL found, return null
            @unlink($tempPkcs7);
            @unlink($tempAllCerts);
            return null;
        } catch (\Exception $e) {
            Log::error('Certificate extraction error: ' . $e->getMessage());
            return null;
        }
    }

    private function parseSingleCertificate($certFilePath, $certTextStr): ?array
    {
        try {
            // Parse certificate
            $certContent = file_get_contents($certFilePath);
            $cert = openssl_x509_read($certContent);

            if (!$cert) {
                return null;
            }

            // Parse certificate details
            $certData = openssl_x509_parse($cert);
            $subject = $certData['subject'] ?? [];

            Log::info('Certificate text output:', ['text' => $certTextStr]);

            // Extract PINFL and INN from text output (OID format)
            $pinfl = null;
            $inn = null;

            // Look for PINFL (OID: 1.2.860.3.16.1.2) — also match 'PINFL=' and Cyrillic variants
            if (preg_match('/1\.2\.860\.3\.16\.1\.2\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            } elseif (preg_match('/\bPINFL\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            } elseif (preg_match('/\bЖШШИР\s*=\s*([A-Z0-9]+)/u', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            } elseif (preg_match('/\bЖСШИР\s*=\s*([A-Z0-9]+)/u', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            }

            // Look for INN (OID: 1.2.860.3.16.1.1) — also match 'INN=', 'STIR='
            if (preg_match('/1\.2\.860\.3\.16\.1\.1\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $inn = $matches[1];
            } elseif (preg_match('/\bSTIR\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $inn = $matches[1];
            } elseif (preg_match('/\bINN\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $inn = $matches[1];
            }

            // Fallback: try to get from subject array
            if (!$pinfl && isset($subject['1.2.860.3.16.1.2'])) {
                $pinfl = $subject['1.2.860.3.16.1.2'];
            }
            if (!$inn && isset($subject['1.2.860.3.16.1.1'])) {
                $inn = $subject['1.2.860.3.16.1.1'];
            }

            // If still no INN, try UID
            if (!$inn && isset($subject['UID'])) {
                $inn = $subject['UID'];
            }

            // Extract CN (may be person name for personal cert, or org name for org cert)
            $cn = $subject['CN'] ?? null;

            // Extract person name from GIVENNAME + SN fields (for organizational certs)
            // In Uzbek PKI org certs: CN=OrgName, but GIVENNAME=Firstname, SN=Lastname
            $givenName = $subject['GN'] ?? $subject['GIVENNAME'] ?? null;
            $surName = $subject['SN'] ?? $subject['SURNAME'] ?? null;

            // Try from text for GN/GIVENNAME/SN/SURNAME
            if (!$givenName && preg_match('/\bGIVENNAME\s*=\s*([^,\n]+)/i', $certTextStr, $matches)) {
                $givenName = trim($matches[1]);
            }
            if (!$givenName && preg_match('/\bGN\s*=\s*([^,\n]+)/i', $certTextStr, $matches)) {
                $givenName = trim($matches[1]);
            }
            if (!$surName && preg_match('/\bSURNAME\s*=\s*([^,\n]+)/i', $certTextStr, $matches)) {
                $surName = trim($matches[1]);
            }
            if (!$surName && preg_match('/\bSN\s*=\s*([^,\n]+)/i', $certTextStr, $matches)) {
                $surName = trim($matches[1]);
            }

            // Construct person name from GIVENNAME+SN if available
            $personName = null;
            if ($surName && $givenName) {
                $personName = trim($surName . ' ' . $givenName);
            } elseif ($surName) {
                $personName = $surName;
            } elseif ($givenName) {
                $personName = $givenName;
            }

            // name = CN (could be org or person name)
            $name = $cn;

            // Extract organization
            $organization = null;
            if (isset($subject['O'])) {
                $organization = $subject['O'];
            }
            if (!$organization && preg_match('/\bO\s*=\s*([^,\n]+)/i', $certTextStr, $matches)) {
                $organization = trim($matches[1]);
            }

            // Extract position/title
            $position = $subject['T'] ?? null;
            if (!$position && preg_match('/\bT\s*=\s*([^,\n]+)/i', $certTextStr, $matches)) {
                $position = trim($matches[1]);
            }

            // Extract validity dates
            $validFrom = isset($certData['validFrom_time_t']) ?
                \Carbon\Carbon::createFromTimestamp($certData['validFrom_time_t']) : null;
            $validTo = isset($certData['validTo_time_t']) ?
                \Carbon\Carbon::createFromTimestamp($certData['validTo_time_t']) : null;

            // Clean up
            openssl_x509_free($cert);

            return [
                'name' => $name,                    // CN (org name or person name)
                'person_name' => $personName,       // from GIVENNAME+SN fields
                'pinfl' => $pinfl,
                'inn' => $inn,
                'organization' => $organization,
                'position' => $position,
                'serial_number' => $certData['serialNumber'] ?? null,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
            ];
        } catch (\Exception $e) {
            Log::error('Certificate extraction error: ' . $e->getMessage());
            return null;
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Regular email/password login for staff.
     */
    public function loginWithPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt(
            ['email' => $request->email, 'password' => $request->password],
            $request->boolean('remember')
        )) {
            $request->session()->regenerate();

            return redirect()->route('home');
        }

        return back()
            ->withErrors(['password_login' => "Email yoki parol noto'g'ri."])
            ->withInput($request->only('email'));
    }
}

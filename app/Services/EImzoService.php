<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EImzoService
{
    /**
     * Generate a random challenge for E-IMZO authentication
     */
    public function getChallenge(): array
    {
        $challenge = Str::random(32);

        return [
            'success' => true,
            'challenge' => $challenge,
        ];
    }

    /**
     * Verify PKCS7 signature and extract certificate information
     */
    public function verifyPkcs7(string $pkcs7Base64, string $challenge): ?array
    {
        try {
            $pkcs7Data = base64_decode($pkcs7Base64);

            // Extract certificate info from PKCS7
            $certInfo = $this->extractCertInfoFromPKCS7($pkcs7Data);

            return $certInfo;
        } catch (\Exception $e) {
            Log::error('E-IMZO verification error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract certificate information from PKCS7 data
     */
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

            // Process each certificate
            foreach ($certMatches[1] as $certPem) {
                $tempSingleCert = tempnam(sys_get_temp_dir(), 'cert_');
                file_put_contents($tempSingleCert, $certPem);

                // Get detailed text output with all OIDs
                $certText = [];
                exec("openssl x509 -in {$tempSingleCert} -text -noout 2>&1", $certText);
                $certTextStr = implode("\n", $certText);

                // Check if this certificate has PINFL (means it's a user certificate, not CA)
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

    /**
     * Parse a single certificate and extract relevant information
     */
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

            // Extract PINFL and INN from text output (OID format)
            $pinfl = null;
            $inn = null;

            // Look for PINFL (OID: 1.2.860.3.16.1.2)
            if (preg_match('/1\.2\.860\.3\.16\.1\.2\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            } elseif (preg_match('/\bPINFL\s*=\s*([A-Z0-9]+)/i', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            } elseif (preg_match('/\bЖШШИР\s*=\s*([A-Z0-9]+)/u', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            } elseif (preg_match('/\bЖСШИР\s*=\s*([A-Z0-9]+)/u', $certTextStr, $matches)) {
                $pinfl = $matches[1];
            }

            // Look for INN (OID: 1.2.860.3.16.1.1)
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

            // Extract person name from GIVENNAME + SN fields
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
                'name' => $name,
                'person_name' => $personName,
                'pinfl' => $pinfl,
                'inn' => $inn,
                'organization' => $organization,
                'position' => $position,
                'serial_number' => $certData['serialNumber'] ?? null,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
            ];
        } catch (\Exception $e) {
            Log::error('Certificate parsing error: ' . $e->getMessage());
            return null;
        }
    }
}

<?php

namespace App\Modules\Customers\Services;

/**
 * Verifică parolele clienților migrați din WordPress, fără a le cunoaște: hash-uri phpass „portabile”
 * ($P$ / $H$, WordPress < 6.8), bcrypt cu prefix „$wp” (WordPress ≥ 6.8: bcrypt peste HMAC-SHA384) și bcrypt simplu.
 * După prima autentificare reușită, parola este re-hash-uită nativ și hash-ul vechi este șters.
 */
class LegacyPasswordVerifier
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function supports(string $hash): bool
    {
        return str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')
            || str_starts_with($hash, '$wp$2y$') || str_starts_with($hash, '$2y$');
    }

    public function check(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$wp')) {
            $prehash = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));

            return password_verify($prehash, substr($hash, 3));
        }
        if (str_starts_with($hash, '$2y$')) {
            return password_verify($password, $hash);
        }
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, $this->phpass($password, $hash));
        }

        return false;
    }

    /** Algoritmul phpass „portable” (MD5 iterat), folosit de WordPress înainte de versiunea 6.8. */
    public function phpass(string $password, string $setting): string
    {
        $output = '*0';
        if (substr($setting, 0, 2) === $output) {
            $output = '*1';
        }
        $id = substr($setting, 0, 3);
        if ($id !== '$P$' && $id !== '$H$') {
            return $output;
        }
        $countLog2 = strpos(self::ITOA64, $setting[3]);
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }
        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $output;
        }
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).$this->encode64($hash, 16);
    }

    private function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}

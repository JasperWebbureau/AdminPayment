<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\App\Settings\Settings;

final class DirectDebitSettings
{
    public static function enabled(): bool { return self::value('enabled', '0') === '1'; }
    public static function noticeDays(): int { return max(1, min(60, (int)self::value('notice_days', '14'))); }

    public static function save(bool $enabled, int $days): void
    {
        if ($days < 1 || $days > 60) { throw new \InvalidArgumentException('Kies 1 tot en met 60 dagen aankondigingstermijn.'); }
        new Settings();
        Settings::get(self::key('enabled'), ['value' => '0', 'label' => 'Automatische factuurincasso actief', 'input_type' => '']);
        Settings::get(self::key('notice_days'), ['value' => '14', 'label' => 'Incasso aankondigingstermijn in dagen', 'input_type' => '']);
        Settings::set(self::key('enabled'), $enabled ? '1' : '0');
        Settings::set(self::key('notice_days'), (string)$days);
        (new Settings())->save();
    }

    private static function value(string $name, string $fallback): string
    {
        new Settings();
        $setting = Settings::get(self::key($name));
        return is_array($setting) ? (string)($setting['value'] ?? $fallback) : $fallback;
    }

    private static function key(string $name): string
    {
        if (!defined('__ADMIN_TENANT_ID__')) { throw new \LogicException('De administratietenant ontbreekt.'); }
        return 'administration_directdebit_' . $name . '_' . hash('sha256', (string)__ADMIN_TENANT_ID__);
    }
}

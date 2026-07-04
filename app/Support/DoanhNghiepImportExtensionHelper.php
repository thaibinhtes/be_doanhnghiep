<?php

namespace App\Support;

class DoanhNghiepImportExtensionHelper
{
    /**
     * Preset mở rộng có sẵn — chỉ áp dụng khi được cấu hình gửi lên.
     *
     * @var array<string, array<string, mixed>>
     */
    public const PRESETS = [
        'vsic_code' => [
            'type' => 'regex',
            'pattern' => '/^(\d+)\s*:/u',
            'group' => 1,
            'fallbackPattern' => '/^(\d+)/u',
        ],
        'vsic_code_list' => [
            'type' => 'regex_list',
            'separator' => '/\s*[,;]\s*/',
            'item' => 'vsic_code',
        ],
    ];

    /**
     * Gợi ý cấu hình mở rộng cho FE (mặc định không bật).
     *
     * @return list<array{field: string, fieldLabel: string, extensions: list<array{key: string, label: string}>}>
     */
    public static function availableExtensions(): array
    {
        return [
            [
                'field' => 'nganhNgheKDChinh',
                'fieldLabel' => 'Ngành nghề KD chính',
                'extensions' => [
                    [
                        'key' => 'vsic_code',
                        'label' => 'Trích mã VSIC (vd: 2391:Mô tả → 2391)',
                    ],
                ],
            ],
            [
                'field' => 'nganhNgheKD',
                'fieldLabel' => 'Ngành nghề KD',
                'extensions' => [
                    [
                        'key' => 'vsic_code_list',
                        'label' => 'Trích mã VSIC từ danh sách (phân tách , hoặc ;)',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|array<string, mixed>>|null  $extensions
     * @return array<string, mixed>
     */
    public static function apply(array $data, ?array $extensions): array
    {
        if ($extensions === null || $extensions === []) {
            return $data;
        }

        foreach ($extensions as $fieldKey => $extension) {
            if (!is_string($fieldKey) || !array_key_exists($fieldKey, $data)) {
                continue;
            }

            $data[$fieldKey] = self::transform($data[$fieldKey], $extension);
        }

        return $data;
    }

    /**
     * @param  string|array<string, mixed>  $extension
     */
    private static function transform(mixed $value, string|array $extension): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $config = is_string($extension)
            ? (self::PRESETS[$extension] ?? null)
            : $extension;

        if (!is_array($config) || !isset($config['type'])) {
            return $value;
        }

        return match ($config['type']) {
            'regex' => self::applyRegex($value, $config),
            'regex_list' => self::applyRegexList($value, $config),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function applyRegex(mixed $value, array $config): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $pattern = (string) ($config['pattern'] ?? '');
        $group = (int) ($config['group'] ?? 1);

        if ($pattern !== '' && preg_match($pattern, $text, $matches) && isset($matches[$group])) {
            return trim((string) $matches[$group]);
        }

        $fallback = (string) ($config['fallbackPattern'] ?? '');
        if ($fallback !== '' && preg_match($fallback, $text, $matches) && isset($matches[$group])) {
            return trim((string) $matches[$group]);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function applyRegexList(mixed $value, array $config): ?string
    {
        if (!is_string($value)) {
            return self::applyRegex($value, self::resolveItemConfig($config));
        }

        $separator = (string) ($config['separator'] ?? '/\s*[,;]\s*/');
        $parts = preg_split($separator, trim($value)) ?: [];
        $itemConfig = self::resolveItemConfig($config);

        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $part) => self::applyRegex($part, $itemConfig) ?? '',
            array_filter($parts, static fn ($part) => trim((string) $part) !== ''),
        ))));

        return $codes === [] ? null : implode('; ', $codes);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function resolveItemConfig(array $config): array
    {
        $item = $config['item'] ?? null;

        if (is_string($item) && isset(self::PRESETS[$item])) {
            return self::PRESETS[$item];
        }

        if (is_array($item)) {
            return $item;
        }

        return self::PRESETS['vsic_code'];
    }
}

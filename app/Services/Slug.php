<?php
namespace App\Services;

/**
 * app/Services/Slug.php
 *
 * Перетворення довільного рядка (українською/російською/латиницею)
 * у URL-safe slug:
 *   «Захід сонця у Карпатах»  →  'zakhid-sontsya-u-karpatakh'
 *
 * Етапи:
 *   1) транслітерація кирилиці у латиницю (стандарт BGN/PCGN-подібний);
 *   2) lowercase;
 *   3) усе, що не [a-z0-9], → '-';
 *   4) повторні дефіси і обрізання дефісів по краях.
 *
 * Окремо є uniqueFor(): отримує функцію exists($slug)->bool і дописує
 * '-2', '-3', ..., доки слаг не стане унікальним. Це використовується
 * під час створення альбому / фото / тега, бо у БД є UNIQUE-індекси.
 */
final class Slug
{
    /**
     * Таблиця транслітерації. Послідовність важлива:
     * 'щ' треба обробити ДО 'ш', інакше воно вкоротиться до 'sh'+'ch'.
     * Тут це не критично, бо ми робимо strtr() — він замінює усі ключі
     * одночасно, але впорядкування підкреслює намір.
     */
    private const MAP = [
        // Великі
        'А' => 'A',  'Б' => 'B',  'В' => 'V',  'Г' => 'H',  'Ґ' => 'G',
        'Д' => 'D',  'Е' => 'E',  'Є' => 'Ye', 'Ж' => 'Zh', 'З' => 'Z',
        'И' => 'Y',  'І' => 'I',  'Ї' => 'Yi', 'Й' => 'Y',  'К' => 'K',
        'Л' => 'L',  'М' => 'M',  'Н' => 'N',  'О' => 'O',  'П' => 'P',
        'Р' => 'R',  'С' => 'S',  'Т' => 'T',  'У' => 'U',  'Ф' => 'F',
        'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch',
        'Ь' => '',   'Ю' => 'Yu', 'Я' => 'Ya', "'" => '',   '’' => '',
        // ros. дублікати, які можуть зустрічатися
        'Ё' => 'Yo', 'Ы' => 'Y',  'Э' => 'E',  'Ъ' => '',

        // Малі
        'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'h',  'ґ' => 'g',
        'д' => 'd',  'е' => 'e',  'є' => 'ye', 'ж' => 'zh', 'з' => 'z',
        'и' => 'y',  'і' => 'i',  'ї' => 'yi', 'й' => 'y',  'к' => 'k',
        'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',  'п' => 'p',
        'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',  'ф' => 'f',
        'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ь' => '',   'ю' => 'yu', 'я' => 'ya',
        'ё' => 'yo', 'ы' => 'y',  'э' => 'e',  'ъ' => '',
    ];

    /**
     * Звичайний slug без жодних запитів до БД.
     * Повертає пустий рядок ТІЛЬКИ якщо вхід виявися повністю не-літерним.
     */
    public static function make(string $value, int $maxLength = 120): string
    {
        // 1) транслітерація
        $value = strtr($value, self::MAP);

        // 2) lowercase
        $value = mb_strtolower($value, 'UTF-8');

        // 3) усе, що НЕ a-z0-9, перетворюємо у '-'
        //    (не використовуємо ICU, щоб не залежати від додаткових модулів PHP)
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        // 4) прибрати дефіси по краях і повторні
        $value = trim($value, '-');

        // 5) обмежити довжину (БД-поля slug мають VARCHAR(120-220))
        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
            $value = trim($value, '-');
        }

        return $value;
    }

    /**
     * Унікальний slug: якщо $exists($slug) повертає true,
     * додаємо суфікси '-2', '-3', ...
     *
     * Приклад використання у моделі Album:
     *   $slug = Slug::uniqueFor($title, function(string $s) use ($pdo): bool {
     *       $stmt = $pdo->prepare('SELECT 1 FROM albums WHERE slug=?');
     *       $stmt->execute([$s]);
     *       return (bool)$stmt->fetchColumn();
     *   });
     *
     * @param callable(string): bool $exists
     */
    public static function uniqueFor(string $value, callable $exists, int $maxLength = 120): string
    {
        $base = self::make($value, $maxLength) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($exists($slug)) {
            // У разі дописування суфікса треба слідкувати, щоб не перевищити maxLength
            $suffix = '-' . $i;
            $trimTo = max(1, $maxLength - mb_strlen($suffix));
            $slug = mb_substr($base, 0, $trimTo) . $suffix;
            $i++;
            if ($i > 1000) {
                // Захист від нескінченного циклу: дописуємо випадковий хвіст.
                $slug = mb_substr($base, 0, max(1, $maxLength - 9)) . '-' . bin2hex(random_bytes(4));
                break;
            }
        }
        return $slug;
    }
}

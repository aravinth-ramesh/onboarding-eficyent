<?php

namespace App\Support;

use App\Models\Question;

/**
 * Give the Industry Classification (MCC) question its option list.
 *
 * The codes only existed in the client's picker, so the question carried no
 * options and every admin surface — View Details, the audit trail, the PDF —
 * printed the stored code ("5942") instead of the industry it means
 * ("Book Stores"). Seeding them here gives both sides one source.
 */
class IndustryClassificationOptions
{
    /**
     * @return int number of questions updated
     */
    public static function apply(): int
    {
        $options = self::options();
        if ($options === []) {
            return 0;
        }

        return Question::where('type', 'mcc')
            ->get()
            ->each(fn (Question $question) => $question->update(['options' => $options]))
            ->count();
    }

    /**
     * Flat {value, label, group} list — the group is carried so the client can
     * keep rendering the picker by sector.
     *
     * @return array<int, array{value: string, label: string, group: string}>
     */
    private static function options(): array
    {
        $options = [];

        foreach (config('mcc_codes', []) as $group => $codes) {
            foreach ($codes as $code => $label) {
                $options[] = ['value' => (string) $code, 'label' => $label, 'group' => $group];
            }
        }

        return $options;
    }
}

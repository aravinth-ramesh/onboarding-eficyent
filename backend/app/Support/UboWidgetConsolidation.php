<?php

namespace App\Support;

use App\Models\Question;
use App\Models\UserAnswer;

/**
 * Retire the duplicate `ubo` widget in favour of the owner table (EOP-49).
 *
 * The ownership section carried two overlapping ways to capture beneficial
 * owners, so clients could be asked for the same people twice and reviewers
 * had to read both. Neither was a superset: the table held the residential
 * address and the source-of-wealth / passport / proof-of-address uploads, the
 * `ubo` widget held ID type, ID number and the PEP flag.
 *
 * UboTableColumns adds the missing fields to the table, making it the single
 * complete record. This then carries existing `ubo` answers across and
 * deactivates the widget.
 *
 * Deactivated, never deleted: the historical answers stay in the database and
 * remain visible to reviewers, which matters for an application already
 * decided on that evidence.
 */
class UboWidgetConsolidation
{
    /** ubo owner key => owner-table column key. */
    private const FIELD_MAP = [
        'full_name' => 'full_legal_name',
        'ownership_percent' => '%_ownership',
        'nationality' => 'nationality',
        'date_of_birth' => 'date_of_birth',
        'id_type' => 'id_type',
        'id_number' => 'id_number',
        'is_pep' => 'is_pep',
    ];

    /**
     * @return array{answers_migrated: int, widgets_retired: int}
     */
    public static function apply(): array
    {
        $answersMigrated = 0;
        $widgetsRetired = 0;

        foreach (Question::where('type', 'ubo')->get() as $uboQuestion) {
            $table = self::ownerTableInSameGroup($uboQuestion);

            if ($table) {
                $answersMigrated += self::migrateAnswers($uboQuestion, $table);
            }

            if ($uboQuestion->is_active) {
                $uboQuestion->update(['is_active' => false]);
                $widgetsRetired++;
            }
        }

        return ['answers_migrated' => $answersMigrated, 'widgets_retired' => $widgetsRetired];
    }

    /**
     * The owner table this widget duplicates: a table question in the same
     * group carrying a nationality column. Only an unambiguous single match
     * is used — anything else is left for a human to decide.
     */
    private static function ownerTableInSameGroup(Question $uboQuestion): ?Question
    {
        $candidates = Question::where('type', 'table')
            ->where('question_group_id', $uboQuestion->question_group_id)
            ->get()
            ->filter(fn (Question $q) => in_array('nationality', array_column($q->options['columns'] ?? [], 'key'), true));

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private static function migrateAnswers(Question $uboQuestion, Question $table): int
    {
        $migrated = 0;

        foreach (UserAnswer::where('question_id', $uboQuestion->id)->get() as $uboAnswer) {
            $owners = json_decode((string) $uboAnswer->value, true);
            if (! is_array($owners) || $owners === []) {
                continue;
            }

            $existing = UserAnswer::where('question_id', $table->id)
                ->where('user_onboarding_id', $uboAnswer->user_onboarding_id)
                ->first();

            // Never overwrite owners already captured in the table — the
            // client's own entry wins over anything we carry across.
            if ($existing && self::hasRows($existing->value)) {
                continue;
            }

            $rows = array_values(array_map(self::mapOwner(...), array_filter($owners, 'is_array')));
            if ($rows === []) {
                continue;
            }

            if ($existing) {
                $existing->update(['value' => json_encode($rows)]);
            } else {
                UserAnswer::create([
                    'user_id' => $uboAnswer->user_id,
                    'question_id' => $table->id,
                    'user_onboarding_id' => $uboAnswer->user_onboarding_id,
                    'value' => json_encode($rows),
                ]);
            }

            $migrated++;
        }

        return $migrated;
    }

    /**
     * @param  array<string, mixed>  $owner
     * @return array<string, mixed>
     */
    private static function mapOwner(array $owner): array
    {
        $row = [];
        foreach (self::FIELD_MAP as $from => $to) {
            if (array_key_exists($from, $owner) && $owner[$from] !== null && $owner[$from] !== '') {
                $row[$to] = $owner[$from];
            }
        }

        return $row;
    }

    private static function hasRows(mixed $value): bool
    {
        $rows = json_decode((string) $value, true);

        return is_array($rows) && array_filter($rows, fn ($row) => is_array($row) && array_filter($row, fn ($v) => $v !== null && $v !== '')) !== [];
    }
}

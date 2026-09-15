<?php

namespace App\Console\Commands;

use App\Support\BankAccountFormat;
use App\Support\ControlMeasureFields;
use App\Support\CountryListQuestions;
use App\Support\CountryOfIncorporationField;
use App\Support\CountryRegistrationCatalog;
use App\Support\DuplicateRegistrationQuestions;
use App\Support\FieldValidationRules;
use App\Support\IndustryClassificationOptions;
use App\Support\PhoneColumnTypes;
use App\Support\UboTableColumns;
use App\Support\UboWidgetConsolidation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bring an existing database in line with the question and registration
 * configuration that ships in code.
 *
 * Most of this platform's behaviour is configuration held in the database —
 * question types, validation rules, table columns, the country catalog — while
 * the definitions live in code. A deploy runs migrations, not seeders, so every
 * time one of those definitions changed it needed its own data migration to
 * reach an existing installation. Forget one and the symptom is silent: the
 * client form is fine because it falls back to config, while the admin panel
 * shows stale or empty data. Four separate bug reports traced to exactly that.
 *
 * This is the one command to run after deploying. Every applier is idempotent,
 * so running it repeatedly is safe and a second run should report no changes.
 */
class SyncOnboardingConfig extends Command
{
    protected $signature = 'onboarding:sync-config
                            {--dry-run : Report what would change without writing anything}';

    protected $description = 'Apply the shipped question and country configuration to this database';

    /**
     * Ordered, because some appliers deliberately overwrite what an earlier one
     * filled in. ControlMeasureFields and BankAccountFormat must follow
     * FieldValidationRules: that one merges with `$existing + $new` and never
     * overwrites, so raising a limit or widening a format needs its own pass
     * afterwards. Do not reorder without reading those three together.
     *
     * @var array<class-string, string>
     */
    private const APPLIERS = [
        UboTableColumns::class => 'UBO and director table columns',
        UboWidgetConsolidation::class => 'Consolidated UBO widgets',
        CountryListQuestions::class => 'Country list questions',
        CountryOfIncorporationField::class => 'Country of incorporation field',
        IndustryClassificationOptions::class => 'Industry classification (MCC) options',
        FieldValidationRules::class => 'Field validation rules',
        ControlMeasureFields::class => 'AML control-measure field limits',
        PhoneColumnTypes::class => 'Phone columns inside tables',
        BankAccountFormat::class => 'Account number / IBAN format',
        DuplicateRegistrationQuestions::class => 'Duplicate registration questions',
        CountryRegistrationCatalog::class => 'Country registration catalog',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — every change is rolled back at the end.');
        }

        DB::beginTransaction();

        $rows = [];
        $total = 0;

        try {
            foreach (self::APPLIERS as $applier => $label) {
                $changed = self::countChanges($applier::apply());
                $total += $changed;
                $rows[] = [$label, $changed === 0 ? '—' : (string) $changed];
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Sync failed, nothing was written: '.$e->getMessage());

            return self::FAILURE;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        $this->newLine();
        $this->table(['Configuration', 'Records changed'], $rows);

        if ($total === 0) {
            $this->info('Already in sync — nothing to change.');
        } elseif ($dryRun) {
            $this->warn("{$total} record(s) would change. Re-run without --dry-run to apply.");
        } else {
            $this->info("{$total} record(s) updated.");
        }

        return self::SUCCESS;
    }

    /**
     * Appliers report their work differently — a count, a breakdown, or nothing
     * at all. Normalise rather than forcing eleven classes to change shape.
     */
    private static function countChanges(mixed $result): int
    {
        if (is_int($result)) {
            return $result;
        }

        if (is_array($result)) {
            return (int) array_sum(array_filter($result, 'is_int'));
        }

        return 0;
    }
}

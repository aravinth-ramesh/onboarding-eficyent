<?php

namespace Tests\Unit;

use App\Services\DocumentIntelligence\Rules\DateExtractor;
use App\Services\DocumentIntelligence\Rules\DocumentClassifier;
use App\Services\DocumentIntelligence\Rules\MrzReader;
use Tests\TestCase;

class RulesEngineTest extends TestCase
{
    // ICAO 9303 specimen passport MRZ (valid check digits, expiry 2012-04-15).
    private const SPECIMEN_MRZ = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\n"
        . "L898902C36UTO7408122F1204159ZE184226B<<<<<10";

    public function test_classifier_identifies_certificate_of_incorporation(): void
    {
        $result = (new DocumentClassifier())->classify(
            'CERTIFICATE OF INCORPORATION — The Registrar of Companies hereby certify that '
            . 'ACME LTD is incorporated under the Companies Act 2006. Company Number 1234567.'
        );

        $this->assertSame('certificate_of_incorporation', $result['type']);
        $this->assertGreaterThanOrEqual(14, $result['score']);
    }

    public function test_classifier_negative_anchors_separate_articles_from_certificate(): void
    {
        $result = (new DocumentClassifier())->classify(
            'ARTICLES OF ASSOCIATION and MEMORANDUM OF ASSOCIATION of ACME LTD. '
            . 'The share capital of the company is divided into ordinary shares.'
        );

        $this->assertSame('articles_of_association', $result['type']);
    }

    public function test_classifier_returns_other_for_unknown_text(): void
    {
        $result = (new DocumentClassifier())->classify('A short grocery list: apples, oranges, bananas.');

        $this->assertSame('other', $result['type']);
    }

    public function test_date_extractor_reads_labeled_dates_in_common_formats(): void
    {
        $extractor = new DateExtractor();

        $this->assertSame('2024-12-31', $extractor->labeled('Licence valid until 31 December 2024.', 'expiry')?->toDateString());
        $this->assertSame('2024-12-31', $extractor->labeled('Valid until: 31/12/2024', 'expiry')?->toDateString());
        $this->assertSame('2026-07-13', $extractor->labeled('Date of issue: July 13, 2026', 'issue')?->toDateString());
        $this->assertSame('2026-07-13', $extractor->labeled('Statement date 2026-07-13', 'issue')?->toDateString());
        $this->assertSame('2026-06-14', $extractor->labeled('Dated this 14th June 2026', 'issue')?->toDateString());
    }

    public function test_date_extractor_ignores_unlabeled_dates(): void
    {
        $this->assertNull((new DateExtractor())->labeled('The meeting of 01/05/2026 was adjourned.', 'expiry'));
    }

    public function test_date_extractor_disambiguates_day_first_when_obvious(): void
    {
        // 13 can only be a day.
        $this->assertSame(
            '2026-07-13',
            (new DateExtractor())->labeled('Expires on 07/13/2026', 'expiry')?->toDateString()
        );
    }

    public function test_mrz_reader_parses_specimen_passport(): void
    {
        $result = (new MrzReader())->read("REPUBLIC OF UTOPIA PASSPORT\n" . self::SPECIMEN_MRZ);

        $this->assertNotNull($result);
        $this->assertSame('2012-04-15', $result->dateOfExpiry);
        $this->assertTrue($result->valid, 'specimen check digits should verify');
    }

    public function test_mrz_reader_returns_null_without_mrz(): void
    {
        $this->assertNull((new MrzReader())->read('Just an ordinary letter about the weather.'));
    }
}

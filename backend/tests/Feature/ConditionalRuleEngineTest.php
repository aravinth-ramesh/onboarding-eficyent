<?php

namespace Tests\Feature;

use App\Models\ConditionalRule;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Services\ConditionalRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The backend engine read only parent_question_id, so a rule keyed on the
 * virtual `country_code` field compared against null and behaved as though the
 * country had never been chosen — while the browser engine evaluated the same
 * rule correctly. Two engines disagreeing on one rule is the bug.
 */
class ConditionalRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    private ConditionalRuleEngine $engine;

    private Question $target;

    private Question $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new ConditionalRuleEngine();

        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);
        $this->target = Question::create([
            'question_group_id' => $group->id, 'label' => 'GSTIN', 'type' => 'text',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);
        $this->parent = Question::create([
            'question_group_id' => $group->id, 'label' => 'Regulated?', 'type' => 'radio',
            'is_required' => false, 'order' => 2, 'is_active' => true,
        ]);
    }

    private function rule(array $attributes): ConditionalRule
    {
        return ConditionalRule::create(array_merge([
            'question_id' => $this->target->id,
            'parent_question_id' => $this->parent->id,
            'comparison_type' => 'equals',
            'trigger_value' => 'yes',
            'action' => 'show',
            'logical_operator' => 'and',
            'is_active' => true,
        ], $attributes));
    }

    public function test_a_country_rule_reads_the_virtual_field(): void
    {
        $rule = $this->rule(['parent_field' => 'country_code', 'trigger_value' => 'IN']);

        $this->assertTrue(
            $this->engine->evaluate([$rule], ['country_code' => 'IN']),
            'a matching country must show the question',
        );
    }

    public function test_a_country_rule_does_not_fire_for_another_country(): void
    {
        $rule = $this->rule(['parent_field' => 'country_code', 'trigger_value' => 'IN']);

        $this->assertFalse($this->engine->evaluate([$rule], ['country_code' => 'SG']));
    }

    public function test_a_country_rule_is_not_satisfied_by_a_question_answer(): void
    {
        // The old code read $answers[parent_question_id]; feeding the country
        // under the question id must not be mistaken for the country field.
        $rule = $this->rule(['parent_field' => 'country_code', 'trigger_value' => 'IN']);

        $this->assertFalse($this->engine->evaluate([$rule], [$this->parent->id => 'IN']));
    }

    public function test_an_ordinary_rule_still_reads_the_parent_question(): void
    {
        $rule = $this->rule([]);

        $this->assertTrue($this->engine->evaluate([$rule], [$this->parent->id => 'yes']));
        $this->assertFalse($this->engine->evaluate([$rule], [$this->parent->id => 'no']));
    }

    public function test_a_hide_rule_inverts_the_outcome(): void
    {
        $rule = $this->rule(['action' => 'hide']);

        $this->assertFalse($this->engine->evaluate([$rule], [$this->parent->id => 'yes']));
        $this->assertTrue($this->engine->evaluate([$rule], [$this->parent->id => 'no']));
    }

    public function test_a_multi_select_answer_matches_any_selected_value(): void
    {
        $rule = $this->rule(['trigger_value' => 'crypto']);

        $this->assertTrue(
            $this->engine->evaluate([$rule], [$this->parent->id => json_encode(['fx', 'crypto'])]),
        );
    }

    public function test_an_unanswered_country_shows_nothing(): void
    {
        $rule = $this->rule(['parent_field' => 'country_code', 'trigger_value' => 'IN']);

        $this->assertFalse($this->engine->evaluate([$rule], []));
    }
}

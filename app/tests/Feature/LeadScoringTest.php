<?php

namespace Tests\Feature;

use App\Application\Scoring\LeadScoringService;
use App\Application\Scoring\ScoreProspect;
use App\Filament\Pages\ViewProspect;
use App\Models\BuyingSignal;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadScoringTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, list<string>, ?string, int}> */
    public static function scores(): array
    {
        return [
            'all high' => ['high', 'high', ['high', 'high'], 'high', 100],
            'all low without signals' => ['low', 'low', [], 'low', 0],
            'low signal' => ['low', 'low', ['low'], 'low', 3],
            'medium mappings' => ['medium', 'medium', ['medium'], 'medium', 48],
            'signals cap' => ['high', 'high', ['high', 'high', 'high'], 'high', 100],
            'no evidence' => ['high', 'high', [], null, 60],
            'no signals' => ['high', 'high', [], 'high', 70],
            'mixed signals' => ['high', 'high', ['high', 'medium'], 'high', 93],
            'five low signals' => ['high', 'high', ['low', 'low', 'low', 'low', 'low'], 'high', 85],
        ];
    }

    #[DataProvider('scores')]
    public function test_exact_scoring_formula(string $fit, string $relevance, array $strengths, ?string $quality, int $expected): void
    {
        $score = app(LeadScoringService::class)->calculate($fit, $relevance, array_map(fn (string $strength): array => ['strength' => $strength], $strengths), $quality);
        $this->assertSame($expected, $score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_recalculation_persists_and_ui_displays_the_score(): void
    {
        $prospect = Prospect::factory()->create();
        $signal = BuyingSignal::factory()->for($prospect)->create();
        $action = app(ScoreProspect::class);
        $this->assertSame(85, $action->execute($prospect));
        $this->assertSame(85, $prospect->fresh()->score);
        $signal->update(['strength' => 'low']);
        $this->assertSame(73, $action->execute($prospect));
        $this->assertSame(73, $prospect->fresh()->score);
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs(User::factory()->for($prospect->organization)->create())->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertSee('73 / 100')->assertSee('Medium Priority');
    }
}

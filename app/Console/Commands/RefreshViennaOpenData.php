<?php

namespace App\Console\Commands;

use App\Services\ViennaOpenData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('vienna:refresh')]
#[Description('Refresh cached Vienna Open Data layers; only overwrites cache when content changed')]
class RefreshViennaOpenData extends Command
{
    public function handle(ViennaOpenData $vienna): int
    {
        $failed = 0;

        foreach (ViennaOpenData::LAYERS as $layer) {
            try {
                $changed = $vienna->refresh($layer);
                $this->info($changed
                    ? "Updated {$layer} (content changed)."
                    : "{$layer} unchanged.");
            } catch (Throwable $e) {
                $failed++;
                $this->error("Failed to refresh {$layer}: ".$e->getMessage());
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

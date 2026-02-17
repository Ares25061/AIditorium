<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Constraint\IsEmpty;
use function PHPUnit\Framework\isEmpty;

class DestroyAllFiles extends Command
{
    protected $signature = 'app:destroy-all-files';

    protected $description = 'Снести все локальные файлы и из БД.';

    public function handle()
    {
        File::query()->delete();
        $storageFiles = Storage::allFiles();
        if (empty($storageFiles)) {
            $this->error('Storage files not found');
            return;
        }
        foreach ($storageFiles as $storageFile) {
            Storage::delete($storageFile);
        }
        $this->info(count($storageFiles) . ' files deleted');
    }
}

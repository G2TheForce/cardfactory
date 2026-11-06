<?php

namespace Tests\Unit;

use App\Support\Executable;
use PHPUnit\Framework\TestCase;

class ExecutableTest extends TestCase
{
    public function test_file_urls_work_for_unix_and_windows_paths(): void
    {
        $this->assertSame('file:///tmp/chrome/page.html', Executable::fileUrl('/tmp/chrome/page.html'));
        $this->assertSame('file:///C:/Users/me/app/storage/page.html', Executable::fileUrl('C:\Users\me\app/storage/page.html'));
    }

    public function test_backslash_paths_are_treated_as_paths_not_command_names(): void
    {
        $this->assertTrue(Executable::isPath('C:\Program Files\Google\Chrome\Application\chrome.exe'));
        $this->assertTrue(Executable::isPath('/usr/bin/gs'));
        $this->assertFalse(Executable::isPath('gs'));
    }

    public function test_find_returns_the_first_existing_candidate(): void
    {
        $this->assertNull(Executable::find(['/definitely/missing/chrome', 'definitely-not-a-real-command-xyz', null, '']));
        $this->assertSame(PHP_BINARY, Executable::find(['/definitely/missing/chrome', PHP_BINARY]));
        $this->assertNotNull(Executable::find(['php']));
    }
}

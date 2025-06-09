<?php

namespace Tests\Entry;

use Dezull\Unarchiver\Adapter\AdapterInterface;
use Dezull\Unarchiver\Entry\Entry;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Entry::class)]
class EntryTest extends TestCase
{
    public function test_extract_and_overwrite(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())
            ->method('extract')
            ->with('/tmp/out', ['entry1.txt'], true);

        $entry = (new Entry($adapter))->setPath('entry1.txt');

        $entry->extract('/tmp/out');
    }

    public function test_extract_and_dont_overwrite(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())
            ->method('extract')
            ->with('/tmp/out', ['entry1.txt'], false);

        $entry = (new Entry($adapter))->setPath('entry1.txt');

        $entry->extract('/tmp/out', overwrite: false);
    }

    public function test_set_modification_time_with_date_time(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $entry = (new Entry($adapter))->setPath('entry1.txt');
        $time = date_create_from_format('Y-m-d H:i:s', '2025-06-05 08:35:21');

        $entry->setModificationTime($time);

        $this->assertSame($time, $entry->getModificationTime());
    }

    public function test_set_modification_time_with_timestamp(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $entry = (new Entry($adapter))->setPath('entry1.txt');

        $entry->setModificationTime('1749112521');

        $time = date_create_from_format('Y-m-d H:i:s', '2025-06-05 08:35:21');
        $this->assertEquals($time, $entry->getModificationTime());
    }
}

<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Migration\Writer;

use SwagMigrationAssistant\Exception\WriterNotFoundException;

class WriterRegistry implements WriterRegistryInterface
{
    /**
     * @param WriterInterface[] $writers
     */
    public function __construct(private readonly iterable $writers)
    {
    }

    public function getWriter(string $entityName): WriterInterface
    {
        foreach ($this->writers as $writer) {
            if ($writer->supports() === $entityName) {
                return $writer;
            }
        }

        throw new WriterNotFoundException($entityName);
    }
}

<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Shopware6\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\Converter;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class ShopwareConverter extends Converter
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MigrationContextInterface
     */
    protected $migrationContext;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var string
     */
    protected $runId;

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->context = $context;
        $this->migrationContext = $migrationContext;

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $this->runId = $this->migrationContext->getRunUuid();

        $this->generateChecksum($data);
        $convertStructResult = $this->convertData($data);

        if (!empty($this->mainMapping)) {
            $this->updateMainMapping($this->migrationContext, $this->context);
        }

        return $convertStructResult;
    }

    abstract protected function convertData(array $data): ConvertStruct;

    protected function getMappingIdFacade(string $entityName, string $oldIdentifier): ?string
    {
        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            $entityName,
            $oldIdentifier,
            $this->context
        );

        if (empty($mapping)) {
            return null;
        }

        $this->mappingIds[] = $mapping['id'];

        return $mapping['entityUuid'];
    }

    protected function getOrCreateMappingIdFacade(
        string $entityName,
        string $oldIdentifier,
        ?string $newIdentifier = null
    ): ?string {
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $entityName,
            $oldIdentifier,
            $this->context,
            null,
            null,
            $newIdentifier
        );

        $this->mappingIds[] = $mapping['id'];

        return $mapping['entityUuid'];
    }

    protected function getOrCreateMappingMainCompleteFacade(
        string $entityName,
        string $oldIdentifier,
        ?string $newIdentifier = null
    ): array {
        return $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $entityName,
            $oldIdentifier,
            $this->context,
            $this->checksum,
            null,
            $newIdentifier
        );
    }
}

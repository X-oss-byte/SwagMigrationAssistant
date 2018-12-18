<?php declare(strict_types=1);

namespace SwagMigrationNext\Profile\Shopware55\Converter;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use SwagMigrationNext\Migration\Converter\AbstractConverter;
use SwagMigrationNext\Migration\Converter\ConvertStruct;
use SwagMigrationNext\Migration\Logging\LoggingServiceInterface;
use SwagMigrationNext\Migration\MigrationContext;
use SwagMigrationNext\Profile\Shopware55\Logging\Shopware55LogTypes;
use SwagMigrationNext\Profile\Shopware55\Mapping\Shopware55MappingService;
use SwagMigrationNext\Profile\Shopware55\Shopware55Profile;

class TranslationConverter extends AbstractConverter
{
    /**
     * @var ConverterHelperService
     */
    private $helper;

    /**
     * @var Shopware55MappingService
     */
    private $mappingService;

    /**
     * @var string
     */
    private $profileId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var LoggingServiceInterface
     */
    private $loggingService;

    public function __construct(
        Shopware55MappingService $mappingService,
        ConverterHelperService $converterHelperService,
        LoggingServiceInterface $loggingService
    ) {
        $this->helper = $converterHelperService;
        $this->mappingService = $mappingService;
        $this->loggingService = $loggingService;
    }

    public function getSupportedEntityName(): string
    {
        return 'translation';
    }

    public function getSupportedProfileName(): string
    {
        return Shopware55Profile::PROFILE_NAME;
    }

    public function writeMapping(Context $context): void
    {
        $this->mappingService->writeMapping($context);
    }

    public function convert(
        array $data,
        Context $context,
        MigrationContext $migrationContext
    ): ConvertStruct {
        $this->profileId = $migrationContext->getProfileId();
        $this->context = $context;
        $this->runId = $migrationContext->getRunUuid();

        switch ($data['objecttype']) {
//            Does not work currently, because products are mapped with the SKU
//            case 'article':
//                return $this->createProductTranslation($data);
            case 'supplier':
                return $this->createManufacturerProductTranslation($data);
            case 'config_units':
                return $this->createUnitTranslation($data);
            case 'category':
                return $this->createCategoryTranslation($data);
        }

        $this->loggingService->addWarning(
            $this->runId,
            Shopware55LogTypes::NOT_CONVERTABLE_OBJECT_TYPE,
            'Not convert able object type',
            sprintf('Translation of object type "%s" could not converted.', $data['objecttype']),
            [
                'objectType' => $data['objecttype'],
                'data' => $data,
            ]
        );

        return new ConvertStruct(null, $data);
    }

    private function createProductTranslation(array &$data): ConvertStruct
    {
        $sourceData = $data;
        $productTranslation = [];
        $productTranslation['id'] = $this->mappingService->createNewUuid(
            $this->profileId,
            ProductTranslationDefinition::getEntityName(),
            $data['id'],
            $this->context
        );
        $productTranslation['productId'] = $this->mappingService->getUuid(
            $this->profileId,
            ProductDefinition::getEntityName() . '_container',
            $data['objectkey'],
            $this->context
        );

        if (!isset($productTranslation['productId'])) {
            $productTranslation['productId'] = $this->mappingService->getUuid(
                $this->profileId,
                ProductDefinition::getEntityName() . '_mainProduct',
                $data['objectkey'],
                $this->context
            );
        }

        if (!isset($productTranslation['productId'])) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::ASSOCIATION_REQUIRED_MISSING,
                'Associated product not found',
                'Mapping of "product" is missing, but it is a required association for "translation". Import "product" first.',
                [
                    'data' => $data,
                    'missingEntity' => 'product',
                    'requiredFor' => 'translation',
                    'missingImportEntity' => 'product',
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        unset($data['id'], $data['objectkey']);
        $productTranslation['entityDefinitionClass'] = ProductTranslationDefinition::class;

        $objectData = unserialize($data['objectdata'], ['allowed_classes' => false]);

        if (!\is_array($objectData)) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Product-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'Product',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        foreach ($objectData as $key => $value) {
            switch ($key) {
                case 'txtArtikel':
                    $this->helper->convertValue($productTranslation, 'name', $objectData, 'txtArtikel');
                    break;
                case 'txtlangbeschreibung':
                    $this->helper->convertValue($productTranslation, 'descriptionLong', $objectData, 'txtlangbeschreibung');
                    break;
                case 'txtshortdescription':
                    $this->helper->convertValue($productTranslation, 'description', $objectData, 'txtshortdescription');
                    break;
                case 'txtpackunit':
                    $this->helper->convertValue($productTranslation, 'packUnit', $objectData, 'txtpackunit');
                    break;
            }
        }

        $data['objectdata'] = serialize($objectData);
        if (empty($objectData)) {
            unset($data['objectdata']);
        }

        unset($data['objecttype'], $data['objectkey'], $data['objectlanguage'], $data['dirty']);

        $languageData = $this->mappingService->getLanguageUuid($this->profileId, $data['_locale'], $this->context);

        if (isset($languageData['createData'])) {
            $productTranslation['language']['id'] = $languageData['uuid'];
            $productTranslation['language']['localeId'] = $languageData['createData']['localeId'];
            $productTranslation['language']['name'] = $languageData['createData']['localeCode'];
        } else {
            $productTranslation['languageId'] = $languageData['uuid'];
        }

        unset($data['name'], $data['_locale']);

        return new ConvertStruct($productTranslation, $data);
    }

    private function createManufacturerProductTranslation(array &$data): ConvertStruct
    {
        $sourceData = $data;
        $manufacturerTranslation = [];
        $manufacturerTranslation['id'] = $this->mappingService->createNewUuid(
            $this->profileId,
            ProductManufacturerTranslationDefinition::getEntityName(),
            $data['id'],
            $this->context
        );
        $manufacturerTranslation['productManufacturerId'] = $this->mappingService->getUuid(
            $this->profileId,
            ProductManufacturerDefinition::getEntityName(),
            $data['objectkey'],
            $this->context
        );
        unset($data['id'], $data['objectkey']);

        if (!isset($manufacturerTranslation['productManufacturerId'])) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::ASSOCIATION_REQUIRED_MISSING,
                'Associated manufacturer not found',
                'Mapping of "manufacturer" is missing, but it is a required association for "translation". Import "product" first.',
                [
                    'data' => $data,
                    'missingEntity' => 'manufacturer',
                    'requiredFor' => 'translation',
                    'missingImportEntity' => 'product',
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        $manufacturerTranslation['entityDefinitionClass'] = ProductManufacturerTranslationDefinition::class;
        $this->helper->convertValue($manufacturerTranslation, 'name', $data, 'name');

        $objectData = unserialize($data['objectdata'], ['allowed_classes' => false]);

        if (!\is_array($objectData)) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Manufacturer-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'Manufacturer',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        foreach ($objectData as $key => $value) {
            switch ($key) {
                case 'metaTitle':
                    $this->helper->convertValue($manufacturerTranslation, 'metaTitle', $objectData, 'metaTitle');
                    break;
                case 'description':
                    $this->helper->convertValue($manufacturerTranslation, 'description', $objectData, 'description');
                    break;
                case 'metaDescription':
                    $this->helper->convertValue($manufacturerTranslation, 'metaDescription', $objectData, 'metaDescription');
                    break;
                case 'metaKeywords':
                    $this->helper->convertValue($manufacturerTranslation, 'metaKeywords', $objectData, 'metaKeywords');
                    break;
            }
        }

        $data['objectdata'] = serialize($objectData);
        if (empty($objectData)) {
            unset($data['objectdata']);
        } else {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Manufacturer-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'Manufacturer',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        unset($data['objecttype'], $data['objectkey'], $data['objectlanguage'], $data['dirty']);

        $languageData = $this->mappingService->getLanguageUuid($this->profileId, $data['_locale'], $this->context);

        if (isset($languageData['createData'])) {
            $manufacturerTranslation['language']['id'] = $languageData['uuid'];
            $manufacturerTranslation['language']['localeId'] = $languageData['createData']['localeId'];
            $manufacturerTranslation['language']['name'] = $languageData['createData']['localeCode'];
        } else {
            $manufacturerTranslation['languageId'] = $languageData['uuid'];
        }

        unset($data['_locale']);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($manufacturerTranslation, $data);
    }

    private function createUnitTranslation(array $data): ConvertStruct
    {
        $sourceData = $data;

        $unitTranslation = [];
        $unitTranslation['id'] = $this->mappingService->createNewUuid(
            $this->profileId,
            UnitTranslationDefinition::getEntityName(),
            $data['id'],
            $this->context
        );
        $unitTranslation['unitId'] = $this->mappingService->getUuid(
            $this->profileId,
            UnitDefinition::getEntityName(),
            $data['objectkey'],
            $this->context
        );
        unset($data['id'], $data['objectkey']);

        if (!isset($unitTranslation['unitId'])) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::ASSOCIATION_REQUIRED_MISSING,
                'Associated unit not found',
                'Mapping of "unit" is missing, but it is a required association for "translation". Import "product" first.',
                [
                    'data' => $data,
                    'missingEntity' => 'unit',
                    'requiredFor' => 'translation',
                    'missingImportEntity' => 'product',
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        $unitTranslation['entityDefinitionClass'] = UnitTranslationDefinition::class;

        $objectData = unserialize($data['objectdata'], ['allowed_classes' => false]);

        if (!\is_array($objectData)) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Unit-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'Unit',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        /** @var array $objectData */
        $objectData = array_pop($objectData);

        foreach ($objectData as $key => $value) {
            switch ($key) {
                case 'unit':
                    $this->helper->convertValue($unitTranslation, 'shortCode', $objectData, 'unit');
                    break;
                case 'description':
                    $this->helper->convertValue($unitTranslation, 'name', $objectData, 'description');
                    break;
            }
        }

        $data['objectdata'] = serialize($objectData);
        if (empty($objectData)) {
            unset($data['objectdata']);
        } else {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Unit-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'Unit',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        unset($data['objecttype'], $data['objectkey'], $data['objectlanguage'], $data['dirty']);

        $languageData = $this->mappingService->getLanguageUuid($this->profileId, $data['_locale'], $this->context);

        if (isset($languageData['createData'])) {
            $unitTranslation['language']['id'] = $languageData['uuid'];
            $unitTranslation['language']['localeId'] = $languageData['createData']['localeId'];
            $unitTranslation['language']['name'] = $languageData['createData']['localeCode'];
        } else {
            $unitTranslation['languageId'] = $languageData['uuid'];
        }

        unset($data['name'], $data['_locale']);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($unitTranslation, $data);
    }

    private function createCategoryTranslation(array $data): ConvertStruct
    {
        $sourceData = $data;

        $categoryTranslation = [];
        $categoryTranslation['id'] = $this->mappingService->createNewUuid(
            $this->profileId,
            CategoryDefinition::getEntityName(),
            $data['id'],
            $this->context
        );
        $categoryTranslation['categoryId'] = $this->mappingService->getUuid(
            $this->profileId,
            CategoryDefinition::getEntityName(),
            $data['objectkey'],
            $this->context
        );
        unset($data['id'], $data['objectkey']);

        if (!isset($categoryTranslation['categoryId'])) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::ASSOCIATION_REQUIRED_MISSING,
                'Associated category not found',
                'Mapping of "category" is missing, but it is a required association for "translation". Import "category" first.',
                [
                    'data' => $data,
                    'missingEntity' => 'category',
                    'requiredFor' => 'translation',
                    'missingImportEntity' => 'category',
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        $categoryTranslation['entityDefinitionClass'] = CategoryTranslationDefinition::class;

        $objectData = unserialize($data['objectdata'], ['allowed_classes' => false]);

        if (!\is_array($objectData)) {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Category-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'Category',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        // no equivalent in category translation definition
        unset($objectData['streamId'], $objectData['external'], $objectData['externalTarget']);
        foreach ($objectData as $key => $value) {
            switch ($key) {
                case 'description':
                    $this->helper->convertValue($categoryTranslation, 'name', $objectData, 'description');
                    break;
                case 'cmsheadline':
                    $this->helper->convertValue($categoryTranslation, 'cmsHeadline', $objectData, 'cmsheadline');
                    break;
                case 'cmstext':
                    $this->helper->convertValue($categoryTranslation, 'cmsDescription', $objectData, 'cmstext');
                    break;
                case 'metatitle':
                    $this->helper->convertValue($categoryTranslation, 'metaTitle', $objectData, 'metatitle');
                    break;
                case 'metadescription':
                    $this->helper->convertValue($categoryTranslation, 'metaDescription', $objectData, 'metadescription');
                    break;
                case 'metakeywords':
                    $this->helper->convertValue($categoryTranslation, 'metaKeywords', $objectData, 'metakeywords');
                    break;
            }
        }

        $data['objectdata'] = serialize($objectData);
        if (empty($objectData)) {
            unset($data['objectdata']);
        } else {
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::INVALID_UNSERIALIZED_DATA,
                'Invalid unserialized data',
                'Category-Translation-Entity could not converted cause of invalid unserialized object data.',
                [
                    'entity' => 'category',
                    'data' => $data['objectdata'],
                ]
            );

            return new ConvertStruct(null, $sourceData);
        }

        unset($data['objecttype'], $data['objectkey'], $data['objectlanguage'], $data['dirty']);

        $languageData = $this->mappingService->getLanguageUuid($this->profileId, $data['_locale'], $this->context);

        if (isset($languageData['createData'])) {
            $categoryTranslation['language']['id'] = $languageData['uuid'];
            $categoryTranslation['language']['localeId'] = $languageData['createData']['localeId'];
            $categoryTranslation['language']['name'] = $languageData['createData']['localeCode'];
        } else {
            $categoryTranslation['languageId'] = $languageData['uuid'];
        }

        unset($data['name'], $data['_locale']);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($categoryTranslation, $data);
    }
}

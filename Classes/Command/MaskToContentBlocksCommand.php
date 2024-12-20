<?php

declare(strict_types=1);

namespace NH\MaskToContentBlocks\Command;

use MASK\Mask\Definition\ElementDefinition;
use MASK\Mask\Definition\TableDefinition;
use MASK\Mask\Definition\TableDefinitionCollection;
use MASK\Mask\Enumeration\FieldType;
use MASK\Mask\Imaging\PreviewIconResolver;
use MASK\Mask\Utility\AffixUtility;
use MASK\Mask\Utility\TemplatePathUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\ContentBlocks\Builder\ContentBlockBuilder;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeIcon;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Utility\ContentBlockPathUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(tags: [
    [
        'name' => 'console.command',
        'command' => 'mask-to-content-blocks:migrate',
        'description' => 'Migrates all active Mask Elements to Content Blocks.',
        'schedulable' => false,
    ],
])]
class MaskToContentBlocksCommand extends Command
{
    public function __construct(
        protected TableDefinitionCollection $tableDefinitionCollection,
        protected PreviewIconResolver $previewIconResolver,
        protected ContentBlockBuilder $contentBlockBuilder,
        /**
         * @var array{content?: string, backend?: string}
         */
        protected array $maskExtensionConfiguration,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = 'tt_content';
        $targetExtension = $this->extractExtensionFromMaskConfiguration();
        if (!$this->tableDefinitionCollection->hasTable($table)) {
            $output->writeln('No Mask elements found.');
            return Command::SUCCESS;
        }
        $contentElementTableDefinition = $this->tableDefinitionCollection->getTable($table);
        foreach ($contentElementTableDefinition->elements ?? [] as $element) {
            if ($element->hidden) {
                continue;
            }
            /** @var string[] $columns */
            $columns = $element->columns;
            $fieldArray = $this->traverseMaskColumnsRecursive($element, $columns, $contentElementTableDefinition);
            $contentBlockName = str_replace('_', '-', $element->key);
            $name = 'mask/' . $contentBlockName;
            $path = 'EXT:' . $targetExtension . '/' . ContentBlockPathUtility::getRelativeContentElementsPath();
            $yaml = [
                'name' => $name,
                'table' => $table,
                'typeField' => ContentType::CONTENT_ELEMENT->getTypeField(),
                'typeName' => AffixUtility::addMaskCTypePrefix($element->key),
                'prefixFields' => false,
                'title' => $element->label,
                'description' => $element->description,
                'basics' => [
                    'TYPO3/Appearance',
                    'TYPO3/Links',
                ],
                'fields' => $fieldArray,
            ];
            $contentBlock = new LoadedContentBlock(
                $name,
                $yaml,
                new ContentTypeIcon(),
                $targetExtension,
                $path,
                ContentType::CONTENT_ELEMENT,
            );
            $this->contentBlockBuilder->create($contentBlock);
            $contentBlockPath = $path . '/' . $contentBlockName;
            $this->copyFrontendTemplate($element->key, $contentBlockPath);
            $this->copyPreviewTemplate($element->key, $contentBlockPath);
            $this->copyIcon($element->key, $contentBlockPath);
        }
        return Command::SUCCESS;
    }

    protected function extractExtensionFromMaskConfiguration(): string
    {
        if (!array_key_exists('content', $this->maskExtensionConfiguration)) {
            throw new \InvalidArgumentException('Please provide extension where to put Content Blocks in.', 1734639218);
        }
        $parts = explode('/', $this->maskExtensionConfiguration['content']);
        $extNotation = $parts[0];
        $extParts = explode(':', $extNotation);
        $extensionKey = $extParts[1];
        return $extensionKey;
    }

    protected function copyFrontendTemplate(string $contentElementKey, string $targetExtPath): void
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($targetExtPath);
        $absoluteTemplatePath = $absolutePath . '/' . ContentBlockPathUtility::getFrontendTemplatePath();
        $maskFrontendTemplate = TemplatePathUtility::getTemplatePath($this->maskExtensionConfiguration, $contentElementKey);
        if (file_exists($maskFrontendTemplate)) {
            copy($maskFrontendTemplate, $absoluteTemplatePath);
        }
    }

    protected function copyPreviewTemplate(string $contentElementKey, string $targetExtPath): void
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($targetExtPath);
        $absoluteTemplatePath = $absolutePath . '/' . ContentBlockPathUtility::getBackendPreviewPath();
        $backendPreviewTemplatePath = (string)($this->maskExtensionConfiguration['backend'] ?? '');
        $maskPreviewTemplate = TemplatePathUtility::getTemplatePath(
            $this->maskExtensionConfiguration,
            $contentElementKey,
            false,
            GeneralUtility::getFileAbsFileName($backendPreviewTemplatePath)
        );
        if (file_exists($maskPreviewTemplate)) {
            copy($maskPreviewTemplate, $absoluteTemplatePath);
        }
    }

    protected function copyIcon(string $contentElementKey, string $targetExtPath): void
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($targetExtPath);
        $absoluteIconPath = $absolutePath . '/' . ContentBlockPathUtility::getIconPathWithoutFileExtension();
        $previewIconPath = $this->previewIconResolver->getPreviewIconPath($contentElementKey);
        if ($previewIconPath === '') {
            return;
        }
        $fileExtensionParts = explode('.', $previewIconPath);
        $fileExtension = end($fileExtensionParts);
        $absolutePreviewIconPath = Environment::getPublicPath() . $previewIconPath;

        if (file_exists($absolutePreviewIconPath)) {
            copy($absolutePreviewIconPath, $absoluteIconPath . '.' . $fileExtension);
        }
    }

    /**
     * @param string[] $columns
     * @return array<array<string, mixed>>
     */
    protected function traverseMaskColumnsRecursive(ElementDefinition $element, array $columns, TableDefinition $tableDefinition): array
    {
        $fieldArray = [];
        if ($tableDefinition->tca === null) {
            return $fieldArray;
        }
        foreach ($columns as $fieldKey) {
            $field = [];
            $tcaFieldDefinition = $tableDefinition->tca->getField($fieldKey);
            /** @var array<string, mixed> $tca */
            $tca = $tcaFieldDefinition->realTca['config'] ?? [];
            unset($tca['type']);
            try {
                $fieldType = $this->tableDefinitionCollection->getFieldType($fieldKey, $tableDefinition->table);
            } catch (\InvalidArgumentException) {
                $fieldType = FieldType::STRING;
            }
            $contentBlockFieldType = match ($fieldType) {
                FieldType::STRING => 'Text',
                FieldType::INTEGER, FieldType::FLOAT => 'Number',
                FieldType::LINK => 'Link',
                FieldType::DATE, FieldType::TIMESTAMP, FieldType::DATETIME => 'DateTime',
                FieldType::TEXT, FieldType::RICHTEXT => 'Textarea',
                FieldType::CHECK => 'Checkbox',
                FieldType::RADIO => 'Radio',
                FieldType::SELECT => 'Select',
                FieldType::EMAIL => 'Email',
                FieldType::CATEGORY => 'Category',
                FieldType::FILE, FieldType::MEDIA => 'File',
                FieldType::COLORPICKER => 'Color',
                FieldType::FOLDER => 'Folder',
                FieldType::SLUG => 'Slug',
                FieldType::GROUP => 'Relation',
                FieldType::CONTENT, FieldType::INLINE => 'Collection',
                FieldType::TAB => 'Tab',
                FieldType::LINEBREAK => 'Linebreak',
                FieldType::PALETTE => 'Palette',
            };
            $field['type'] = $contentBlockFieldType;
            if ($fieldType !== FieldType::LINEBREAK) {
                $field['identifier'] = $fieldKey;
            }
            if ($tcaFieldDefinition->isCoreField) {
                $field['useExistingField'] = true;
            }
            if (($label = $this->tableDefinitionCollection->getLabel($element->key, $fieldKey, $tableDefinition->table)) !== '') {
                $field['label'] = $label;
            }
            if (($description = $this->tableDefinitionCollection->getDescription($element->key, $fieldKey, $tableDefinition->table)) !== '') {
                $field['description'] = $description;
            }
            $columnsOverrides = [];
            if ($element->hasColumnsOverride($fieldKey)) {
                $columnsOverrideTcaDefinition = $element->getColumnsOverride($fieldKey);
                /** @var array<string, mixed> $columnsOverrides */
                $columnsOverrides = $columnsOverrideTcaDefinition->realTca['config'] ?? [];
            }
            $field = array_merge($field, $tca, $columnsOverrides);
            // Cleanup
            if (($field['nullable'] ?? null) === 0) {
                unset($field['nullable']);
            }
            if (($fieldType === FieldType::SELECT || $fieldType === FieldType::CHECK) && ($field['items'] ?? []) === []) {
                unset($field['items']);
            }
            // Defaults
            if ($fieldType === FieldType::FILE && ($field['allowed'] ?? '') === '') {
                $field['allowed'] = 'common-image-types';
            }
            if ($fieldType === FieldType::MEDIA && ($field['allowed'] ?? '') === '') {
                $field['allowed'] = 'common-media-types';
            }
            if ($fieldType === FieldType::CONTENT) {
                $field['foreign_field'] = 'tx_mask_content_parent_uid';
                $field['foreign_table_field'] = 'tx_mask_content_tablenames';
                $field['foreign_match_fields'] = [
                    'tx_mask_content_role' => $fieldKey,
                ];
                if (!empty($tcaFieldDefinition->cTypes)) {
                    $field['overrideChildTca']['columns']['CType']['config']['default'] = reset($tcaFieldDefinition->cTypes);
                }
                unset($field['overrideChildTca']['columns']['colPos']);
            }
            if ($fieldType === FieldType::TEXT && ($field['format'] ?? false)) {
                $field['renderType'] = 't3editor';
            }
            if ($fieldType->isParentField()) {
                $inlineFields = $this->tableDefinitionCollection->loadInlineFields($fieldKey, $element->key, $element);
                /** @var array<array{fullKey: string}> $array */
                $array = $inlineFields->toArray();
                $inlineColumns = array_map(fn (array $tcaField): string => $tcaField['fullKey'], $array);
                $foreignTableDefinition = $tableDefinition;
                if ($fieldType === FieldType::INLINE) {
                    unset($field['foreign_table']);
                    unset($field['foreign_table_field']);
                    $foreignTableDefinition = $this->tableDefinitionCollection->getTable($fieldKey);
                }
                $field['fields'] = $this->traverseMaskColumnsRecursive($element, $inlineColumns, $foreignTableDefinition);
            }
            $fieldArray[] = $field;
        }
        return $fieldArray;
    }
}

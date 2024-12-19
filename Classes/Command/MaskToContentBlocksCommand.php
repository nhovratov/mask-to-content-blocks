<?php

declare(strict_types=1);

namespace NH\MaskToContentBlocks\Command;

use MASK\Mask\Definition\TableDefinitionCollection;
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
        protected array $maskExtensionConfiguration,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = ContentType::CONTENT_ELEMENT->getTable();
        $targetExtension = $this->extractExtensionFromMaskConfiguration();
        if (!$this->tableDefinitionCollection->hasTable($table)) {
            $output->writeln('No Mask elements found.');
            return Command::SUCCESS;
        }
        $contentElementTableDefinition = $this->tableDefinitionCollection->getTable($table);
        foreach ($contentElementTableDefinition->elements ?? [] as $element) {
            $contentBlockName = str_replace('_', '-', $element->key);
            $name = 'mask/' . $contentBlockName;
            $path = 'EXT:' . $targetExtension . '/' . ContentBlockPathUtility::getRelativeContentElementsPath();
            $yaml = [
                'name' => $name,
                'table' => $table,
                'typeField' => ContentType::CONTENT_ELEMENT->getTypeField(),
                'typeName' => AffixUtility::addMaskCTypePrefix($element->key),
                'title' => $element->label,
                'description' => $element->description,
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

    protected function copyFrontendTemplate(string $contentElementKey, $targetExtPath): void
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($targetExtPath);
        $absoluteTemplatePath = $absolutePath . '/' . ContentBlockPathUtility::getFrontendTemplatePath();
        $maskFrontendTemplate = TemplatePathUtility::getTemplatePath($this->maskExtensionConfiguration, $contentElementKey);
        if (file_exists($maskFrontendTemplate)) {
            copy($maskFrontendTemplate, $absoluteTemplatePath);
        }
    }

    protected function copyPreviewTemplate(string $contentElementKey, $targetExtPath): void
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($targetExtPath);
        $absoluteTemplatePath = $absolutePath . '/' . ContentBlockPathUtility::getBackendPreviewPath();
        $maskPreviewTemplate = TemplatePathUtility::getTemplatePath(
            $this->maskExtensionConfiguration,
            $contentElementKey,
            false,
            GeneralUtility::getFileAbsFileName($this->maskExtensionConfiguration['backend'] ?? '')
        );
        if (file_exists($maskPreviewTemplate)) {
            copy($maskPreviewTemplate, $absoluteTemplatePath);
        }
    }

    protected function copyIcon(string $contentElementKey, $targetExtPath): void
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
}

# Mask to Content Blocks migration

This TYPO3 extension helps you migrate your Mask elements on TYPO3 v13 to TYPO3 CMS Content Blocks, the official TYPO3
extension to define Content Types.

You need a running TYPO3 instance with your loaded Mask elements.

Install this extension via composer:

```
composer req nhovratov/mask-to-content-blocks
```

Then run the migration command. This migration will create the Content Blocks into the same extension, where your Mask
elements are currently loaded.

```
bin/typo3 mask-to-content-blocks:migrate
```

Next, remove Mask and this extension:

```
composer remove mask/mask nhovratov/mask-to-content-blocks
```

## Final steps

This migration command is no guarantee that everything will work perfectly. Check the generated Content Blocks by
yourself and see, if everything is fine. Also, any TypoScript overrides need migration from `lib.maskElement` to
`lib.contentBlock`. Test your frontend template and backend preview. They might need slight adjustments.

For more information, visit the manual migration guide in the Content Blocks documentation: https://docs.typo3.org/permalink/friendsoftypo3-content-blocks:migrations-mask

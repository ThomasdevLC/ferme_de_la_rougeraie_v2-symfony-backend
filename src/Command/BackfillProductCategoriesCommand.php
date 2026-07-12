<?php

namespace App\Command;

use App\Entity\Product;
use App\Enum\ProductCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-product-categories',
    description: 'Fill the category of products that do not have one yet, using keyword rules on the name.',
)]
class BackfillProductCategoriesCommand extends Command
{
    /**
     * Ordered keyword rules — the first matching rule wins, so more specific
     * categories (a drink or a jam made from a fruit) must come before the
     * broader ones. Patterns are matched against the lowercased product name.
     *
     * @var array<array{category: ProductCategory, pattern: string}>
     */
    private const RULES = [
        ['category' => ProductCategory::INFUSION,      'pattern' => '/tisane/u'],
        ['category' => ProductCategory::DRINK,         'pattern' => '/bière|biere|jus|tonic/u'],
        ['category' => ProductCategory::HONEY_JAM,     'pattern' => '/miel|confiture/u'],
        ['category' => ProductCategory::PREPARED_FOOD, 'pattern' => '/rillette|terrine/u'],
        ['category' => ProductCategory::DAIRY_EGG,     'pattern' => '/\boeuf|\blait\b/u'],
        ['category' => ProductCategory::GROCERY,       'pattern' => '/\bnoix\b|châtaigne|chataigne/u'],
        ['category' => ProductCategory::HERB,          'pattern' => '/persil|basilic|coriandre|ciboulette|thym|romarin|menthe|estragon|cerfeuil/u'],
        ['category' => ProductCategory::FRUIT,         'pattern' => '/pomme(?!s? de terre)|\bpoire|fraise|raisin|melon|pastèque|pasteque|kiwi|prune|rhubarbe|citron/u'],
        ['category' => ProductCategory::VEGETABLE,     'pattern' => '/carotte|tomate|laitue|concombre|asperge|poivron|aubergine|courge|brocoli|chou|navet|radis|épinard|betterave|oignon|échalot|echalot|\bail\b|aillet|fenouil|blette|poireau|potimarron|butternut|panais|céleri|patidou|topinambour|piment|haricot|\bpois\b|mange tout|fève|feve|mâche|roquette|mesclun|frisée|batavia|rutabaga|christophine|délicata|delicata|hokkaïdo|hokkaido|buttercup|hongrie|romanesco|kale|choï|rave|pointu|milan|pontoise|bruxelles|champignon|sucrine|chêne|chene|pourpier|cresson|pommes? de terre|\bpdt\b|patate|potiron|scarole|endive/u'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('persist', null, InputOption::VALUE_NONE, 'Write categories to the database (default: dry-run).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $shouldPersist = (bool) $input->getOption('persist');

        /** @var Product[] $products */
        $products = $this->entityManager->getRepository(Product::class)->findBy([
            'category' => null,
            'isDeleted' => false,
        ]);

        if ([] === $products) {
            $io->success('No product left without a category.');

            return Command::SUCCESS;
        }

        $countByCategory = [];
        $unmatched = [];

        foreach ($products as $product) {
            $category = $this->categorize((string) $product->getName());

            if (null === $category) {
                $unmatched[] = (string) $product->getName();

                continue;
            }

            if ($shouldPersist) {
                $product->setCategory($category);
            }

            $countByCategory[$category->value] = ($countByCategory[$category->value] ?? 0) + 1;
        }

        if ($shouldPersist) {
            $this->entityManager->flush();
        }

        $this->report($io, count($products), $countByCategory, $unmatched);

        if ($shouldPersist) {
            $io->success(sprintf('Backfill applied: %d product(s) categorized.', array_sum($countByCategory)));
        } else {
            $io->note('Dry run: nothing was written. Add --persist to apply.');
        }

        return Command::SUCCESS;
    }

    private function categorize(string $name): ?ProductCategory
    {
        $haystack = mb_strtolower($name);

        foreach (self::RULES as $rule) {
            if (1 === preg_match($rule['pattern'], $haystack)) {
                return $rule['category'];
            }
        }

        return null;
    }

    /**
     * @param array<string, int> $countByCategory
     * @param string[]           $unmatched
     */
    private function report(SymfonyStyle $io, int $total, array $countByCategory, array $unmatched): void
    {
        $rows = [];
        // Keep the canonical category order in the report.
        foreach (ProductCategory::cases() as $category) {
            $count = $countByCategory[$category->value] ?? 0;
            if ($count > 0) {
                $rows[] = [$category->value, $category->label(), $count];
            }
        }

        $io->section('Matched by category');
        $io->table(['Enum', 'Libellé', 'Count'], $rows);

        $io->writeln(sprintf(
            'Total: %d · matched: %d · unmatched: %d',
            $total,
            array_sum($countByCategory),
            count($unmatched),
        ));

        if ([] !== $unmatched) {
            $io->section(sprintf('Unmatched (%d) — to review / fix by hand', count($unmatched)));
            $io->listing($unmatched);
        }
    }
}

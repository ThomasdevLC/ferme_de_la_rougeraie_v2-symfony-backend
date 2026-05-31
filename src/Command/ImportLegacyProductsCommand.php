<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-legacy-products',
    description: 'Import products exported from the legacy MongoDB application.',
)]
class ImportLegacyProductsCommand extends Command
{
    private const DEFAULT_IMAGE = 'default.jpg';

    /**
     * @var array<string, ProductUnit>
     */
    private const UNIT_MAP = [
        'kg' => ProductUnit::KG,
        'piece' => ProductUnit::PIECE,
        'botte' => ProductUnit::BUNDLE,
        'bouquet' => ProductUnit::BUNCH,
        'litre' => ProductUnit::LITER,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('json-file', InputArgument::REQUIRED, 'Path to the MongoDB JSON export.')
            ->addOption('persist', null, InputOption::VALUE_NONE, 'Write imported products to the database.')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email of the admin who will own imported products.')
            ->addOption('images-dir', null, InputOption::VALUE_REQUIRED, 'Optional legacy images directory. When omitted, default.jpg is used.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jsonFile = (string) $input->getArgument('json-file');
        $shouldPersist = (bool) $input->getOption('persist');
        $imagesDir = $input->getOption('images-dir');

        if (!is_file($jsonFile) || !is_readable($jsonFile)) {
            $io->error(sprintf('JSON file not found or not readable: %s', $jsonFile));

            return Command::FAILURE;
        }

        try {
            $rows = json_decode((string) file_get_contents($jsonFile), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $io->error(sprintf('Invalid JSON: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        if (!is_array($rows) || !array_is_list($rows)) {
            $io->error('The JSON export must contain a list of products.');

            return Command::FAILURE;
        }

        if (null !== $imagesDir && !is_dir($imagesDir)) {
            $io->error(sprintf('Images directory not found: %s', $imagesDir));

            return Command::FAILURE;
        }

        $admin = $this->findAdmin($input, $io, $shouldPersist);
        if ($shouldPersist && null === $admin) {
            return Command::FAILURE;
        }

        $stats = [
            'products' => 0,
            'defaultImages' => 0,
            'copiedImages' => 0,
            'ignoredIntervals' => 0,
        ];
        $products = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $io->error(sprintf('Product at index %d is not a JSON object.', $index));

                return Command::FAILURE;
            }

            try {
                $products[] = $this->createProduct($row, $imagesDir, $stats);
            } catch (\InvalidArgumentException $exception) {
                $io->error(sprintf('Product at index %d: %s', $index, $exception->getMessage()));

                return Command::FAILURE;
            }

            ++$stats['products'];
        }

        if ($shouldPersist) {
            if (!$this->assertNoExistingProducts($products, $io)) {
                return Command::FAILURE;
            }

            $this->copyImages($products, $imagesDir);

            foreach ($products as $product) {
                $product->setUser($admin);
                $this->entityManager->persist($product);
            }

            $this->entityManager->flush();
        }

        $io->table(
            ['Products', 'Default images', 'Copied images', 'Ignored non-KG intervals'],
            [[
                $stats['products'],
                $stats['defaultImages'],
                $stats['copiedImages'],
                $stats['ignoredIntervals'],
            ]],
        );

        if ($shouldPersist) {
            $io->success('Legacy products imported.');
        } else {
            $io->note('Dry run only: no product was written to the database. Add --persist and --admin-email to import.');
        }

        return Command::SUCCESS;
    }

    private function findAdmin(InputInterface $input, SymfonyStyle $io, bool $shouldPersist): ?User
    {
        if (!$shouldPersist) {
            return null;
        }

        $email = $input->getOption('admin-email');
        if (!is_string($email) || '' === trim($email)) {
            $io->error('The --admin-email option is required with --persist.');

            return null;
        }

        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$admin instanceof User || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            $io->error(sprintf('Admin user not found: %s', $email));

            return null;
        }

        return $admin;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int>   $stats
     */
    private function createProduct(array $row, ?string $imagesDir, array &$stats): Product
    {
        $name = $this->requireString($row, 'name');
        $unit = $this->mapUnit($this->requireString($row, 'unit'));
        $image = $this->resolveImage($this->requireString($row, 'image'), $imagesDir, $stats);

        if (!isset($row['price']) || !is_numeric($row['price'])) {
            throw new \InvalidArgumentException('Missing or invalid "price".');
        }

        $product = new Product();
        $product->setName($name);
        $product->setPrice((int) $row['price']);
        $product->setUnit($unit);
        $product->setInter(ProductUnit::KG === $unit ? $this->requirePositiveInterval($row) : null);
        $product->setIsDisplayed((bool) ($row['isDisplayed'] ?? false));
        $product->setHasStock(false);
        $product->setStock(null);
        $product->setLimited((bool) ($row['limited'] ?? false));
        $product->setDiscount(false);
        $product->setDiscountText(null);
        $product->setImage($image);

        if (ProductUnit::KG !== $unit && isset($row['interval'])) {
            ++$stats['ignoredIntervals'];
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requireString(array $row, string $key): string
    {
        if (!isset($row[$key]) || !is_string($row[$key]) || '' === trim($row[$key])) {
            throw new \InvalidArgumentException(sprintf('Missing or invalid "%s".', $key));
        }

        return trim($row[$key]);
    }

    private function mapUnit(string $legacyUnit): ProductUnit
    {
        $key = mb_strtolower(trim($legacyUnit));

        if (!isset(self::UNIT_MAP[$key])) {
            throw new \InvalidArgumentException(sprintf('Unsupported unit "%s".', $legacyUnit));
        }

        return self::UNIT_MAP[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requirePositiveInterval(array $row): float
    {
        if (!isset($row['interval']) || !is_numeric($row['interval']) || (float) $row['interval'] <= 0) {
            throw new \InvalidArgumentException('Missing or invalid "interval" for a KG product.');
        }

        return (float) $row['interval'];
    }

    /**
     * @param array<string, int> $stats
     */
    private function resolveImage(string $legacyImage, ?string $imagesDir, array &$stats): string
    {
        if (null === $imagesDir) {
            ++$stats['defaultImages'];

            return self::DEFAULT_IMAGE;
        }

        $filename = basename($legacyImage);
        $extension = mb_strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $source = rtrim($imagesDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        if ($filename !== $legacyImage || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || !is_file($source)) {
            ++$stats['defaultImages'];

            return self::DEFAULT_IMAGE;
        }

        ++$stats['copiedImages'];

        return $filename;
    }

    /**
     * @param Product[] $products
     */
    private function assertNoExistingProducts(array $products, SymfonyStyle $io): bool
    {
        $repository = $this->entityManager->getRepository(Product::class);
        $existingNames = [];

        foreach ($products as $product) {
            $existingProduct = $repository->findOneBy([
                'name' => mb_convert_case(trim((string) $product->getName()), MB_CASE_TITLE, 'UTF-8'),
                'price' => $product->getPrice(),
                'unit' => $product->getUnit(),
                'image' => $product->getImage(),
            ]);

            if ($existingProduct instanceof Product) {
                $existingNames[] = $product->getName();
            }
        }

        if ([] === $existingNames) {
            return true;
        }

        $io->error(sprintf(
            'Import refused: %d identical legacy product(s) already exist. Example(s): %s',
            count($existingNames),
            implode(', ', array_slice($existingNames, 0, 5)),
        ));

        return false;
    }

    /**
     * @param Product[] $products
     */
    private function copyImages(array $products, ?string $imagesDir): void
    {
        if (null === $imagesDir) {
            return;
        }

        foreach ($products as $product) {
            $filename = $product->getImage();
            if (self::DEFAULT_IMAGE === $filename) {
                continue;
            }

            $source = rtrim($imagesDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
            $destination = dirname(__DIR__, 2).'/public/uploads/images/'.$filename;
            if (!is_file($destination) && !copy($source, $destination)) {
                throw new \InvalidArgumentException(sprintf('Unable to copy image "%s".', $filename));
            }
        }
    }
}

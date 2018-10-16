<?php
declare(strict_types=1);

use Getopt\{
    Getopt,
    Option
};
use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

if (!\is_readable($root . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

/** @var array $settings */
$settings = \json_decode(
    (string) \file_get_contents($root . '/local/settings.json'),
    true
);
/** @var EasyDB $db */
$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);

/**
 * @var Getopt $getopt
 *
 * This defines the Command Line options.
 *
 * These two are equivalent:
 *     php create-client.php -p foo
 *     php create-client php --public=key=foo
 */
$getopt = new Getopt([
    new Option('p', 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option('c', 'comment', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'administrator', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->parse();

/** @var string $publicKey */
$publicKey = $getopt->getOption('publickey');
/** @var string $comment */
$comment = $getopt->getOption('comment') ?? '';
/** @var bool $admin */
$admin = $getopt->getOption('administrator') ?? false;

/** @var SigningPublicKey $publicKeyObj */
// Make sure it's a valid public key:
try {
    $publicKeyObj = new SigningPublicKey(
        Base64UrlSafe::decode($publicKey)
    );
} catch (\Throwable $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

// Generate a unique ID for the user:
/** @var string $newPublicId */
$newPublicId = Base64UrlSafe::encode(\random_bytes(24));

$db->beginTransaction();
$db->insert(
    'chronicle_clients',
    [
        'isAdmin' => !empty($admin),
        'publicid' => $newPublicId,
        'publickey' => $publicKey
    ]
);
if ($db->commit()) {
    // Success.
    if (!empty($isAdmin)) {
        echo "\t" . 'Client (' . $newPublicId . ') created successfully with administrative privileges!', PHP_EOL;
    } else {
        echo "\t" . 'Client (' . $newPublicId . ') created successfully!', PHP_EOL;
    }
} else {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();
    echo $errorInfo[0], PHP_EOL;
    exit(1);
}

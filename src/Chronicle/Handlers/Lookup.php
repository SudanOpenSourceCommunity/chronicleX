<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\FilesystemException,
    Exception\HashNotFound,
    Exception\InvalidInstanceException,
    HandlerInterface
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Lookup
 * @package ParagonIE\Chronicle\Handlers
 */
class Lookup implements HandlerInterface
{
    /** @var string */
    protected $method = 'index';

    /**
     * Lookup constructor.
     * @param string $method
     */
    public function __construct(string $method = 'index')
    {
        $this->method = $method;
    }

    /**
     * The handler gets invoked by the router. This accepts a Request
     * and returns a Response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     *
     * @throws FilesystemException
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        try {
            // Whitelist of acceptable methods:
            switch ($this->method) {
                case 'instances':
                    return $this->getInstances();
                    break;
                case 'export':
                    return $this->exportChain();
                case 'lasthash':
                    return $this->getLastHash();
                case 'hash':
                    if (!empty($args['hash'])) {
                        return $this->getByHash($args);
                    }
                    break;
                case 'since':
                    if (!empty($args['hash'])) {
                        return $this->getSince($args);
                    }
                    break;
            }
        } catch (\Throwable $ex) {
            return Chronicle::errorResponse($response, $ex->getMessage());
        }
        return Chronicle::errorResponse($response, 'Unknown method: '.$this->method);
    }

    /**
     * Get list of Chronicle instances.
     *
     * @return ResponseInterface
     */
    public function getInstances(): ResponseInterface
    {
        /** @var array<string, array<string, string>> $settings */
        $settings = Chronicle::getSettings();

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => \array_keys($settings['instances']) ?? [],
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Gets the entire Blakechain.
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     * @return ResponseInterface
     * @throws FilesystemException
     * @throws InvalidInstanceException
     */
    public function exportChain(): ResponseInterface
    {
        list($chain, $meta) = $this->getFullChain();

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $chain ?? [],
                'meta' => $meta ?? [],
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get information about a particular entry, given its hash.
     *
     * @param array $args
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     * @throws HashNotFound
     */
    public function getByHash(array $args = []): ResponseInterface
    {
        /** @var array<int, array<string, string>> $record */
        $record = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash,
                 created,
                 publickey,
                 signature
             FROM
                 " . Chronicle::getTableName('chain') . "
             WHERE
                 currhash = ?
                 OR summaryhash = ?
            ",
            $args['hash'],
            $args['hash']
        );
        if (!$record) {
            throw new HashNotFound('No record found matching this hash.');
        }
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $record
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * List the latest current record
     *
     * @return ResponseInterface
     *
     * @throws FilesystemException
     */
    public function getLastHash(): ResponseInterface
    {
        /** @var array<string, string> $record */
        $record = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash,
                 created,
                 publickey,
                 signature
             FROM
                 " . Chronicle::getTableName('chain') . "
             ORDER BY id DESC LIMIT 1
            "
        );
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $record,
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get updates to the chain since a given hash
     *
     * @param array $args
     * @param int $page
     * @param int $perPage
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     * @throws HashNotFound
     * @throws InvalidInstanceException
     */
    public function getSince(array $args = [], int $page = 1, int $perPage = 5): ResponseInterface
    {
        /** @var int $id */
        $id = Chronicle::getDatabase()->cell(
            "SELECT id
             FROM  " . Chronicle::getTableName('chain') . "
             WHERE currhash = ? OR summaryhash = ?
             ORDER BY id ASC
            ",
            $args['hash'],
            $args['hash']
        );
        if (!$id) {
            throw new HashNotFound('No record found matching this hash.');
        }

        /** @var string $queryCondition */
        $queryCondition = '
            id > ?
        ';

        /** 
        * @var string $paginationCondition
        * @var array<string, int> $meta
        */
        list($paginationCondition, $meta) = Chronicle::getPagination(
            'chain', $page, $perPage, $queryCondition, [
                $id,
            ]
        );

        /** @var array<int, array<string, string>> $since */
        $since = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash,
                 created,
                 publickey,
                 signature
             FROM  " . Chronicle::getTableName('chain') . "
             WHERE " . $queryCondition . "
                   " . $paginationCondition
            , $id
        );

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $since,
                'meta' => $meta,
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get the paginated chain, as-is, as of the time of the request.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return array
     * @throws InvalidInstanceException
     */
    protected function getFullChain(int $page = 1, int $perPage = 5): array
    {
        $chain = [];

        /** 
        * @var string $paginationCondition
        * @var array<string, int> $meta
        */
        list($paginationCondition, $meta) = Chronicle::getPagination(
            'chain', $page, $perPage
        );

        /** @var array<int, array<string, string>> $rows */
        $rows = Chronicle::getDatabase()->run(
            "SELECT *
             FROM " . Chronicle::getTableName('chain') . "
             ORDER BY id ASC
            " . $paginationCondition
        );
        /** @var array<string, string> $row */
        foreach ($rows as $row) {
            $chain[] = [
                'contents' => $row['data'],
                'prev' => $row['prevhash'],
                'hash' => $row['currhash'],
                'summary' => $row['summaryhash'],
                'created' => $row['created'],
                'publickey' => $row['publickey'],
                'signature' => $row['signature']
            ];
        }
        return [
            $chain,
            $meta,
        ];
    }
}

<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\FilesystemException,
    HandlerInterface
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Index
 *
 * URI endpoint that lists the endpoints available
 *
 * @package ParagonIE\Chronicle\Handlers
 */
class Index implements HandlerInterface
{
    /**
     * The handler gets invoked by the router. This accepts a Request
     * and returns a Response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        $signingKey = Chronicle::getSigningKey();
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'public-key' => $signingKey->getPublicKey()->getString(),
                'results' => $this->getRoutes($request)
            ],
            $signingKey
        );
    }

    /**
     * Get the available routes. If the request is authenticated, this will
     * also include the routes the client has access to.
     *
     * @param RequestInterface $request
     * @return array
     */
    public function getRoutes(RequestInterface $request): array
    {
        return [
            [
                'uri' => '/chronicle/lasthash',
                'description' => 'Get information about the latest entry in this Chronicle'
            ], [
                'uri' => '/chronicle/lookup/{hash}',
                'description' => 'Lookup the information for the given hash'
            ], [
                'uri' => '/chronicle/since/{hash}',
                'description' => 'List all new entries since a given hash'
            ], [
                'uri' => '/chronicle/export',
                'description' => 'Export the entire Chronicle'
            ], [
                'uri' => '/chronicle/replica',
                'description' => 'List of Chronicles being replicated onto this one (and other options)'
            ], [
                'uri' => '/chronicle',
                'description' => 'API method description'
            ], [
                'uri' => '/chronicle/publish',
                'description' => 'Publish a new message to this Chronicle.',
                'note' => 'Approved clients only.'
            ], [
                'uri' => '/chronicle/register',
                'description' => 'Approve a new client.',
                'required-fields' => [
                    'request-time' => 'The time of your request.',
                    'publickey' => 'Public key of the client to be removed'
                ],
                'note' => 'Administrators only'
            ], [
                'uri' => '/chronicle/revoke',
                'description' => 'Revoke a client\'s access',
                'required-fields' => [
                    'request-time' => 'The time of your request.',
                    'clientid' => 'Unique ID of the client to be removed',
                    'publickey' => 'Public key of the client to be removed'
                ],
                'note' => 'Administrators only'
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Danipa\Sdk\Http;

use Danipa\Sdk\Errors\ResponseParseError;
use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\Internal\MalformedResponse;
use Danipa\Sdk\ResponseMeta;

/**
 * A successful (2xx) response, body not yet interpreted.
 *
 * @internal
 */
final class ApiResponse
{
    public function __construct(
        public readonly string $body,
        public readonly ResponseMeta $meta,
    ) {
    }

    /**
     * Decode the body as a JSON object and hand it to `$factory`. Any shape
     * problem — invalid JSON, a non-object root, a missing or mistyped field —
     * becomes a {@see ResponseParseError}.
     *
     * @template T
     * @param callable(array<string, mixed>, ResponseMeta): T $factory
     * @return T
     * @throws ResponseParseError
     */
    public function parse(callable $factory): mixed
    {
        try {
            return $factory(Json::decodeObject($this->body), $this->meta);
        } catch (MalformedResponse $e) {
            throw $this->parseError($e);
        }
    }

    /**
     * Decode the body as a JSON array of objects and map each through
     * `$factory`. Any shape problem becomes a {@see ResponseParseError}.
     *
     * @template T
     * @param callable(array<array-key, mixed>): T $factory
     * @return list<T>
     * @throws ResponseParseError
     */
    public function parseList(callable $factory): array
    {
        try {
            $decoded = Json::decode($this->body);
            if (!\is_array($decoded) || !array_is_list($decoded)) {
                throw new MalformedResponse('expected a JSON array at the root of the response body');
            }
            $out = [];
            foreach ($decoded as $i => $item) {
                if (!\is_array($item)) {
                    throw new MalformedResponse(sprintf('element [%d] must be an object', $i));
                }
                $out[] = $factory($item);
            }
            return $out;
        } catch (MalformedResponse $e) {
            throw $this->parseError($e);
        }
    }

    private function parseError(MalformedResponse $e): ResponseParseError
    {
        return new ResponseParseError(
            sprintf('Danipa SDK: could not parse the %d response body: %s', $this->meta->status, $e->getMessage()),
            $this->meta->status,
            $this->body,
            $this->meta->requestId,
            $this->meta->idempotencyKey,
            $e,
        );
    }
}

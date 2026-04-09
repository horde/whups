<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Generate OpenSearch XML descriptor.
 *
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Controller;

use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenSearchController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Registry $registry,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $webroot = $this->registry->get('webroot', 'whups');
        $name = $this->registry->get('name', 'whups') . ' (' . $webroot . ')';
        $themesFs = $this->registry->get('themesfs', 'whups');

        $iconPath = $themesFs . '/default/graphics/whups.png';
        $icon = is_file($iconPath)
            ? base64_encode(file_get_contents($iconPath))
            : '';

        $xml = <<<XML
            <OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
              <ShortName>{$name}</ShortName>
              <SearchForm>{$webroot}</SearchForm>
              <Url type="text/html"
                   method="get"
                   template="{$webroot}/ticket/">
                <Param name="id" value="{searchTerms}"/>
              </Url>
              <Image height="16" width="16">data:image/png;base64,{$icon}</Image>
              <InputEncoding>UTF-8</InputEncoding>
            </OpenSearchDescription>
            XML;

        return $this->xmlResponse($xml);
    }
}

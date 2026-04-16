<?php

declare(strict_types=1);

/**
 * PSR-15 controller: Search results RSS feed.
 *
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Controller\Rss;

use Horde\Whups\Controller\ResponseTrait;
use Horde\Whups\Service\TicketSorter;
use Horde\Whups\Service\UrlGenerator;
use Horde_Registry;
use Horde_Themes;
use Horde_Url;
use Horde_Variables;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whups_Driver;
use Whups_Form_Search;

class SearchRssController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Whups_Driver $driver,
        private readonly Horde_Registry $registry,
        private readonly TicketSorter $sorter,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $vars = Horde_Variables::getDefaultVariables();
        $limit = (int) $vars->get('limit');
        $form = new Whups_Form_Search($vars);

        if (!$form->validate($vars, true)) {
            return $this->xmlResponse('<error>' . _("Invalid search") . '</error>', 400);
        }

        $info = $form->getInfo($vars);
        $tickets = $this->driver->getTicketsByProperties($info);
        $this->sorter->sort($tickets, 'date_updated', 'desc');

        $items = $this->buildItems($tickets, $limit);

        $webroot = $this->registry->get('webroot', 'whups');
        $view = new Horde_View(['templatePath' => WHUPS_TEMPLATES . '/rss']);
        $view->xsl = Horde_Themes::getFeedXsl();
        $view->pubDate = htmlspecialchars(date('r'));
        $view->title = _("Search Results");
        $view->items = $items;
        $view->url = new Horde_Url($webroot . '/search');
        $view->rss_url = new Horde_Url($webroot . '/search/rss');
        $view->description = _("Search Results");

        return $this->xmlResponse($view->render('items.rss'));
    }

    /**
     * @param array<int, array<string, mixed>> $tickets
     * @return array<int, array<string, string>>
     */
    private function buildItems(array $tickets, int $limit): array
    {
        $items = [];
        $count = 0;

        foreach (array_keys($tickets) as $i) {
            if ($limit > 0 && $count++ === $limit) {
                break;
            }

            $items[$i] = [
                'title' => htmlspecialchars(sprintf(
                    '[%s] %s',
                    $tickets[$i]['id'],
                    $tickets[$i]['summary'],
                )),
                'description' => htmlspecialchars(sprintf(
                    _("Type: %s; State: %s"),
                    $tickets[$i]['type_name'],
                    $tickets[$i]['state_name'],
                )),
                'url' => $this->urlGenerator->absoluteUrlFor('TicketView', ['id' => (int) $tickets[$i]['id']]),
                'pubDate' => htmlspecialchars(date('r', $tickets[$i]['timestamp'])),
            ];
        }

        return $items;
    }
}

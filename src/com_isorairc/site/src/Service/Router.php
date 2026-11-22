<?php

namespace Isora\Component\Isorairc\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * Gives the lookup and race entries pages a clean URL (the menu item's own address).
 */
class Router extends RouterView
{
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        $this->registerView(new RouterViewConfiguration('ratings'));
        $this->registerView(new RouterViewConfiguration('entries'));

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}

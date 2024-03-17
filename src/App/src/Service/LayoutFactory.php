<?php

declare(strict_types=1);

namespace App\Service;

use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Psr\Container\ContainerInterface;

use function array_merge;
use function basename;
use function in_array;
use function glob;

final class LayoutFactory
{
    /**
     * The purpose of me doing this is so that when in single page mode we are only serving slightly dynamic
     * pages. Which means that its just some config blah blah. So, I thought, why not just create a model
     * per page and just assign them to the layout as properties. I've never done it this way so.. I thought
     * why not. Plus its A LOT different than Axleus, so it gives another example of how flexible
     * laminas and mezzio are. Even when using the same components :P
     */
    public function __invoke(ContainerInterface $container): ModelInterface
    {
        $config   = $container->get('config');
        $settings = $config['settings'];
        $data     = $config['data'];
        $layout   = new ViewModel($settings);
        $layout->setTemplate('layout::default');
        // add the settings
        $layout->setVariable('settings', $settings);

        $nav = new ViewModel();
        $nav->setTemplate('partial::multi-page-nav');
        $nav->setVariables(
            [
                'activeLinks' => $settings['enabledPages'], // creates a activeLink property in the scope of the nav.phtml tmpl
                'enableDropDownMenu' => $settings['enableDropDownMenu'],
            ]
        );

        $hero = new ViewModel();
        $hero->setTemplate('partial::hero');
        if (isset($data['hero'])) {
            $hero->setVariables($data['hero']);
        }

        $footer = new ViewModel();
        $footer->setTemplate('partial::footer');
        $footerVars = array_merge($data['footer'], $data['contact']);
        $footer->setVariables($footerVars);
        $footer->setVariable('siteName', $settings['siteName']);
        // assign layout properties that are models but not pages ;)
        $layout->setVariables(
            [
                'nav'    => $nav,
                'hero'   => $hero,
                'footer' => $footer,
                'enabledPages' => $settings['enabledPages'],
            ]
        );

        if (! $settings['multiPage']) {
            // reset this for single page mode
            $nav->setTemplate('partial::single-page-nav');
            $path  = $config['templates']['paths']['page'][0];
            $files = glob($path . '/*.phtml');
            if (count($files) >= 1 && count($settings['enabledPages']) >= 1) {
                foreach ($files as $file) {
                    $template = basename($file, '.phtml');
                    if (in_array($template, $settings['enabledPages'])) {
                        $activeLinks[] = $template;
                        $child         = new ViewModel();
                        $child->setTemplate('page::' . $template);
                        if (isset($data[$template])) {
                            // This trickery allows us to have a ['settings'][$template] and automatically inject them
                            $child->setVariables($data[$template]);
                        }
                        $layout->setVariable($template, $child);
                    }
                }
            }
        }
        return $layout;
    }
}

<?php 
namespace OmekaTheme\Helper;

use Laminas\View\Helper\AbstractHelper;

class TabManager extends AbstractHelper
{
    /**
     * Collects available page blocks for standard regions of a resource page.
     *
     * Scans the regions 'full_width_main', 'left', 'main', and 'right' and returns only those
     * that contain blocks for the provided resource.
     *
     * @param mixed $resource The resource for which to collect page blocks (typically a resource representation).
     * @return array<string, array> Associative array mapping region names to their blocks arrays; regions without blocks are omitted.
     */
    public function getResourcePageBlocks($resource) {
        $regions = ['full_width_main', 'left', 'main', 'right'];
        $view = $this->getView();
        $tabLayout = $view->themeSetting('tab_navigation_layout');
        $tabContent = $view->themeSetting('tab_navigation_content');
        $regionContent = [];
        foreach ($regions as $region) {
            $regionResourcePageBlocks = $view->resourcePageBlocks($resource, $region);
            if ($regionResourcePageBlocks->hasBlocks()) {
                $regionContent[$region] = $regionResourcePageBlocks->getBlocksArray();
            }
        }
        return $regionContent;
    }

    /**
     * Finds the region that contains a 'tab_navigation' block for a given resource.
     *
     * @param mixed $resource The resource whose page blocks will be inspected.
     * @return string|false The region name containing the 'tab_navigation' block, or `false` if not found.
     */
    public function getTabNavigationRegion($resource) {
        $resourcePageBlocks = $this->getResourcePageBlocks($resource);
        $tabNavigationRegion = false;
        foreach ($resourcePageBlocks as $region => $blockArray) {
            if (array_key_exists('tab_navigation', $blockArray)) {
                $tabNavigationRegion = $region;
            }
        }
        return $tabNavigationRegion;
    }

    /**
     * Render the tab panels for a resource region.
     *
     * Renders the panels partial populated with the blocks for the specified content region and layout.
     *
     * @param mixed  $resource      The resource whose page blocks are rendered.
     * @param string $contentRegion The region from which to collect content blocks (e.g., 'main').
     * @param string $layout        The tab layout to use (e.g., 'vertical' or 'horizontal').
     * @return string The rendered HTML of the tab panels.
     */
    public function renderPanels($resource, $contentRegion = 'main', $layout = 'vertical')
    {
        $view = $this->getView();
        $tabContentBlocksArray = $view->resourcePageBlocks($resource, $contentRegion)->getBlocksArray();
        return $view->partial('common/tab-panels.phtml', [
            'resource' => $resource,
            'resourcePageBlockArray' => $tabContentBlocksArray,
            'tabLayout' => $layout
        ]);
    }

    /**
     * Render the tab navigation markup for a resource region.
     *
     * Renders and returns the HTML markup for the tab navigation based on the blocks of the specified content region.
     *
     * @param mixed  $resource      The resource for which tabs are rendered.
     * @param string $contentRegion The region supplying blocks to build the tabs (e.g., 'main').
     * @param string $layout        The tab layout style, commonly 'vertical' or 'horizontal'.
     * @return string The rendered HTML markup for the tab navigation.
     */
    public function renderTabsOnly($resource, $contentRegion = 'main', $layout = 'vertical') 
    {
        $view = $this->getView();
        $tabContentBlocksArray = $view->resourcePageBlocks($resource, $contentRegion)->getBlocksArray();
        return $view->partial('common/tab-navigation-markup.phtml', [
            'resource' => $resource,
            'resourcePageBlocksArray' => $tabContentBlocksArray,
            'layout' => $layout
        ]);
    }

    /**
     * Render a region's blocks with the tab navigation injected.
     *
     * Retrieves the blocks for the given region, inserts a `tab_navigation` block
     * whose content is the rendered tabs for the specified content region and
     * layout, and returns the concatenated HTML for the region.
     *
     * @param mixed  $resource       The resource whose page blocks are rendered.
     * @param string $currentRegion  The region whose block content will receive the tab navigation (e.g., "main", "left", "right").
     * @param string $contentRegion  The region used to build the tab navigation (defaults to "main").
     * @param string $layout         The tab layout, e.g., "vertical" or "horizontal" (defaults to "vertical").
     * @return string The concatenated HTML of the region's blocks including the injected tab navigation.
     */
    public function renderTabsRegion($resource, $currentRegion, $contentRegion = 'main', $layout = 'vertical')
    {
        $view = $this->getView();
        $regionBlockContentArray = $view->resourcePageBlocks($resource, $currentRegion)->getBlocksArray();
        $regionBlockContentArray['tab_navigation'] = $this->renderTabsOnly($resource, $contentRegion, $layout);
        return implode('', $regionBlockContentArray);
    }
}
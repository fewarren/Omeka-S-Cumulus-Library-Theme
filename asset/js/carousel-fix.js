/**
 * Item Carousel Block JavaScript Fix for LibraryTheme
 * 
 * Ensures proper initialization and functionality of the ItemCarouselBlock module
 * that uses Slick Carousel. This script addresses potential conflicts with
 * LibraryTheme's JavaScript and ensures carousels work correctly.
 */

(function() {
    'use strict';

    // Wait for jQuery to be available, then initialize
    function waitForJQuery() {
        if (typeof jQuery !== 'undefined') {
            initializeWithJQuery(jQuery);
        } else {
            // Retry after a short delay
            setTimeout(waitForJQuery, 100);
        }
    }

    // Start waiting for jQuery
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForJQuery);
    } else {
        waitForJQuery();
    }

    function initializeWithJQuery($) {
        // Wait for DOM to be ready
        $(document).ready(function() {

        // Debug logging (only if console is available)
        function debugLog(message) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[CarouselFix] ' + message);
            }
        }

        debugLog('Carousel fix script loaded');

        // Removed critical CSS injection to avoid overriding Slick layout
        // injectCriticalCSS();

        // Check if jQuery and Slick are available
        if (typeof $ === 'undefined') {
            debugLog('ERROR: jQuery not available');
            return;
        }

        if (typeof $.fn.slick === 'undefined') {
            debugLog('WARNING: Slick carousel not yet loaded, will retry');
            // Retry after a short delay to allow Slick to load
            setTimeout(initializeCarouselFix, 500);
            return;
        }

        initializeCarouselFix();
    });

    function injectCriticalCSS() {
        // Inject critical CSS fixes directly into the page head to ensure maximum priority
        var criticalCSS = `
            /* Minimal injected CSS: avoid overriding Slick layout */
            body .carousel-block .slick-slider,
            html body .carousel-block .slick-slider {
                visibility: visible !important;
                opacity: 1 !important;
            }
        `;

        var style = document.createElement('style');
        style.type = 'text/css';
        style.innerHTML = criticalCSS;
        document.head.appendChild(style);

        if (typeof console !== 'undefined' && console.log) {
            console.log('[CarouselFix] Critical CSS injected');
        }
    }

    function initializeCarouselFix() {

        function debugLog(message) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[CarouselFix] ' + message);
            }
        }

        debugLog('Initializing carousel fix');

        // Find all carousel blocks
        var $carouselBlocks = $('.carousel-block');

        if ($carouselBlocks.length === 0) {
            debugLog('No carousel blocks found on this page');
            return;
        }

        debugLog('Found ' + $carouselBlocks.length + ' carousel block(s)');

        // Process each carousel block
        $carouselBlocks.each(function(index) {
            var $block = $(this);
            var $carousel = $block.find('[class*="carousel-"]');

            if ($carousel.length === 0) {
                debugLog('No carousel element found in block ' + index);
                return;
            }

            debugLog('Processing carousel block ' + index);

            // Always apply display fixes first
            applyDisplayFixes($carousel, index);

            // Check if carousel is already initialized
            if ($carousel.hasClass('slick-initialized')) {
                debugLog('Carousel ' + index + ' already initialized, applying additional fixes');
                fixCarouselDisplay($carousel, index);
                return;
            }

            // For ItemCarouselBlock, wait longer for the inline script to initialize
            // The inline script runs in $(document).ready() which might be after our script
            setTimeout(function() {
                if ($carousel.hasClass('slick-initialized')) {
                    debugLog('Carousel ' + index + ' initialized by ItemCarouselBlock, applying fixes');
                    fixCarouselDisplay($carousel, index);
                } else {
                    debugLog('Carousel ' + index + ' not initialized by ItemCarouselBlock, checking...');
                    checkAndFixCarousel($carousel, index);
                }
            }, 500);

            // Final check after a longer delay
            setTimeout(function() {
                if (!$carousel.hasClass('slick-initialized')) {
                    debugLog('Carousel ' + index + ' still not initialized after delay, forcing initialization');
                    checkAndFixCarousel($carousel, index);
                } else {
                    debugLog('Carousel ' + index + ' successfully initialized, applying final fixes');
                    fixCarouselDisplay($carousel, index);
                }
            }, 2000);
        });
    }

    function applyDisplayFixes($carousel, index) {

        function debugLog(message) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[CarouselFix] ' + message);
            }
        }

        debugLog('Applying pre-initialization display fixes to carousel ' + index);

        // Force proper display on carousel container
        $carousel.css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1'
        });

        // Force proper display on carousel items (before Slick initialization)
        $carousel.children('div').each(function() {
            $(this).css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1',
                'position': 'relative'
            });
        });
    }

    function checkAndFixCarousel($carousel, index) {
        
        function debugLog(message) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[CarouselFix] ' + message);
            }
        }
        
        // Check if carousel was initialized by the original script
        if ($carousel.hasClass('slick-initialized')) {
            debugLog('Carousel ' + index + ' successfully initialized by original script');
            
            // Apply additional fixes to ensure proper display
            fixCarouselDisplay($carousel, index);
            return;
        }
        
        debugLog('Carousel ' + index + ' not initialized, attempting manual initialization');

        // Check if carousel is in the process of being initialized
        if ($carousel.data('slick-initializing')) {
            debugLog('Carousel ' + index + ' is already being initialized, skipping');
            return;
        }

        // Mark as initializing to prevent conflicts
        $carousel.data('slick-initializing', true);

        // Try to initialize manually with basic settings
        try {
            $carousel.slick({
                slidesToShow: 1,
                slidesToScroll: 1,
                infinite: false, // Disable infinite to avoid cloning issues
                draggable: true,
                arrows: true,
                dots: true,
                autoplay: false,
                pauseOnHover: true,
                pauseOnFocus: false,
                centerMode: false,
                adaptiveHeight: true,
                responsive: [
                    {
                        breakpoint: 820,
                        settings: {
                            slidesToShow: 1,
                        }
                    }
                ]
            });
            
            debugLog('Carousel ' + index + ' manually initialized successfully');

            // Clear the initializing flag
            $carousel.removeData('slick-initializing');

            // Apply display fixes
            fixCarouselDisplay($carousel, index);

        } catch (error) {
            debugLog('ERROR: Failed to initialize carousel ' + index + ': ' + error.message);
            // Clear the initializing flag even on error
            $carousel.removeData('slick-initializing');
        }
    }
    
    function fixCarouselDisplay($carousel, index) {
        
        function debugLog(message) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('[CarouselFix] ' + message);
            }
        }
        
        debugLog('Applying display fixes to carousel ' + index);
        
        // Ensure proper CSS classes and styles
        $carousel.addClass('slick-carousel-fixed');
        
        // Fix slide display
        $carousel.find('.slick-slide').each(function() {
            var $slide = $(this);
            
            // Ensure slide visibility only; do not override display/float/transform
            $slide.css({
                'visibility': 'visible',
                'opacity': '1'
            });

            // Fix images
            $slide.find('img').css({
                'display': 'block',
                'max-width': '100%',
                'height': 'auto',
                'margin': 'auto'
            });
        });
        
        // Trigger a resize to ensure proper layout
        setTimeout(function() {
            $carousel.slick('setPosition');
            debugLog('Carousel ' + index + ' position reset');
        }, 50);
    }
    
    // Also handle dynamically added carousels
    $(document).on('DOMNodeInserted', function(e) {
        var $target = $(e.target);
        
        if ($target.hasClass('carousel-block') || $target.find('.carousel-block').length > 0) {
            setTimeout(function() {
                initializeCarouselFix();
            }, 100);
        }
    });
    
    // Handle window resize
    $(window).on('resize', function() {
        $('.slick-initialized').each(function() {
            $(this).slick('setPosition');
        });
    });

    } // end initializeWithJQuery

})();

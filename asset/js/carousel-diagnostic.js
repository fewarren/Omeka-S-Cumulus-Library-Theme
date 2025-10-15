/**
 * Item Carousel Diagnostic Script for LibraryTheme
 * 
 * This script provides detailed diagnostic information about carousel functionality
 * and can be used to troubleshoot issues with the ItemCarouselBlock module.
 * 
 * To use: Add ?carousel_debug=1 to any page URL to enable diagnostic output
 */

(function() {
    'use strict';

    // Only run if debug parameter is present
    var urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('carousel_debug')) {
        return;
    }

    console.log('=== CAROUSEL DIAGNOSTIC MODE ENABLED ===');

    // Wait for jQuery to be available
    function waitForJQuery() {
        if (typeof jQuery !== 'undefined') {
            var $ = jQuery;
            $(document).ready(function() {
                setTimeout(runDiagnostics, 1000); // Wait for everything to load
            });
        } else {
            setTimeout(waitForJQuery, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForJQuery);
    } else {
        waitForJQuery();
    }
    
    function runDiagnostics() {
        var $ = jQuery; // Ensure $ is available in this scope
        console.log('\n=== CAROUSEL DIAGNOSTICS ===');

        // Check jQuery
        if (typeof $ === 'undefined') {
            console.error('❌ jQuery is not available');
            return;
        } else {
            console.log('✅ jQuery version:', $.fn.jquery);
        }
        
        // Check Slick
        if (typeof $.fn.slick === 'undefined') {
            console.error('❌ Slick carousel is not available');
        } else {
            console.log('✅ Slick carousel is available');
        }
        
        // Find carousel blocks
        var $carouselBlocks = $('.carousel-block');
        console.log('📊 Found', $carouselBlocks.length, 'carousel block(s)');
        
        if ($carouselBlocks.length === 0) {
            console.log('ℹ️  No carousel blocks found on this page');
            return;
        }
        
        // Analyze each carousel
        $carouselBlocks.each(function(index) {
            console.log('\n--- Carousel Block', index + 1, '---');
            analyzeCarousel($(this), index);
        });
        
        // Check for CSS conflicts
        console.log('\n--- CSS Analysis ---');
        checkCSSConflicts();
        
        // Check for JavaScript errors
        console.log('\n--- JavaScript Analysis ---');
        checkJavaScriptErrors();
    }
    
    function analyzeCarousel($block, index) {
        var $carousel = $block.find('[class*="carousel-"]');
        
        if ($carousel.length === 0) {
            console.warn('⚠️  No carousel element found in block', index + 1);
            return;
        }
        
        var carouselClass = $carousel.attr('class').split(' ').find(cls => cls.includes('carousel-'));
        console.log('🎠 Carousel class:', carouselClass);
        
        // Check initialization
        if ($carousel.hasClass('slick-initialized')) {
            console.log('✅ Carousel is initialized');
            
            // Check slides
            var $slides = $carousel.find('.slick-slide');
            console.log('📄 Slides found:', $slides.length);

            // Check visible and active slides
            var $visibleSlides = $slides.filter(':visible');
            var $activeSlides = $slides.filter('.slick-active');
            console.log('👁️  Visible slides (jQuery):', $visibleSlides.length);
            console.log('✅ Active slides (slick-active):', $activeSlides.length);

            // Track/list/slide metrics
            var $track = $carousel.find('.slick-track');
            var $list = $carousel.find('.slick-list');
            console.log('🎯 Track transform:', $track.css('transform'));
            console.log('📐 Widths - list/track/first slide:', $list.width(), '/', $track.width(), '/', $slides.first().width());

            // Check slide display properties
            $slides.each(function(slideIndex) {
                var $slide = $(this);
                var display = $slide.css('display');
                var visibility = $slide.css('visibility');
                var opacity = $slide.css('opacity');
                var floatVal = $slide.css('float');

                if (slideIndex < 3) { // Only log first 3 slides to avoid spam
                    console.log('  Slide', slideIndex + 1, '- display:', display, 'visibility:', visibility, 'opacity:', opacity, 'float:', floatVal);
                }
            });

            // Check navigation
            var $arrows = $carousel.find('.slick-arrow');
            var $dots = $carousel.find('.slick-dots');
            console.log('🏹 Navigation arrows:', $arrows.length);
            console.log('🔘 Navigation dots:', $dots.length);
            
        } else {
            console.error('❌ Carousel is NOT initialized');
            
            // Check for slides without slick
            var $items = $carousel.find('> div');
            console.log('📦 Raw items found:', $items.length);
            
            // Check if items are stacked vertically
            if ($items.length > 1) {
                var firstTop = $items.eq(0).offset().top;
                var secondTop = $items.eq(1).offset().top;
                
                if (Math.abs(firstTop - secondTop) > 10) {
                    console.warn('⚠️  Items appear to be stacked vertically (not in carousel)');
                } else {
                    console.log('ℹ️  Items appear to be horizontal');
                }
            }
        }
        
        // Check CSS properties
        console.log('🎨 Carousel CSS:');
        console.log('  display:', $carousel.css('display'));
        console.log('  position:', $carousel.css('position'));
        console.log('  overflow:', $carousel.css('overflow'));
        console.log('  width:', $carousel.css('width'));
        console.log('  height:', $carousel.css('height'));
    }
    
    function checkCSSConflicts() {
        // Check for common CSS conflicts
        var conflicts = [];
        
        // Check if LibraryTheme CSS is loaded
        var stylesheets = document.styleSheets;
        var libraryThemeLoaded = false;
        var slickCSSLoaded = false;
        
        for (var i = 0; i < stylesheets.length; i++) {
            var href = stylesheets[i].href;
            if (href) {
                if (href.includes('library.css') || href.includes('library-')) {
                    libraryThemeLoaded = true;
                }
                if (href.includes('slick')) {
                    slickCSSLoaded = true;
                }
            }
        }
        
        console.log('📄 LibraryTheme CSS loaded:', libraryThemeLoaded);
        console.log('📄 Slick CSS loaded:', slickCSSLoaded);
        
        if (!slickCSSLoaded) {
            conflicts.push('Slick CSS not loaded');
        }
        
        // Check for carousel fix CSS
        var carouselFixLoaded = false;
        for (var i = 0; i < stylesheets.length; i++) {
            var href = stylesheets[i].href;
            if (href && href.includes('carousel-fix.css')) {
                carouselFixLoaded = true;
                break;
            }
        }
        
        console.log('🔧 Carousel fix CSS loaded:', carouselFixLoaded);
        
        if (conflicts.length > 0) {
            console.warn('⚠️  Potential CSS conflicts:', conflicts);
        } else {
            console.log('✅ No obvious CSS conflicts detected');
        }
    }
    
    function checkJavaScriptErrors() {
        // Check if carousel fix script is loaded
        var scripts = document.scripts;
        var carouselFixLoaded = false;
        
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src;
            if (src && src.includes('carousel-fix.js')) {
                carouselFixLoaded = true;
                break;
            }
        }
        
        console.log('🔧 Carousel fix JS loaded:', carouselFixLoaded);
        
        // Check for global errors
        var originalError = window.onerror;
        var errors = [];
        
        window.onerror = function(message, source, lineno, colno, error) {
            if (message.toLowerCase().includes('slick') || message.toLowerCase().includes('carousel')) {
                errors.push({
                    message: message,
                    source: source,
                    line: lineno
                });
            }
            
            if (originalError) {
                return originalError.apply(this, arguments);
            }
        };
        
        setTimeout(function() {
            if (errors.length > 0) {
                console.error('❌ JavaScript errors related to carousel:', errors);
            } else {
                console.log('✅ No carousel-related JavaScript errors detected');
            }
        }, 2000);
    }

})();

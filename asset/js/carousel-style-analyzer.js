/**
 * Carousel Style Analyzer for LibraryTheme
 * 
 * This script analyzes the computed styles of carousel elements to identify
 * specific CSS conflicts that prevent proper carousel functionality.
 */

(function() {
    'use strict';

    // Wait for jQuery to be available
    function waitForJQuery() {
        if (typeof jQuery !== 'undefined') {
            initializeAnalyzer(jQuery);
        } else {
            setTimeout(waitForJQuery, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForJQuery);
    } else {
        waitForJQuery();
    }

    function initializeAnalyzer($) {
        window.CarouselStyleAnalyzer = {
        
        analyze: function() {
            console.log('=== CAROUSEL STYLE ANALYSIS ===');
            
            var $carouselBlocks = $('.carousel-block');
            
            if ($carouselBlocks.length === 0) {
                console.log('No carousel blocks found');
                return;
            }
            
            $carouselBlocks.each(function(index) {
                console.log('\n--- Analyzing Carousel Block ' + (index + 1) + ' ---');
                CarouselStyleAnalyzer.analyzeCarouselBlock($(this), index);
            });
        },
        
        analyzeCarouselBlock: function($block, index) {
            var $carousel = $block.find('[class*="carousel-"]');
            
            if ($carousel.length === 0) {
                console.log('No carousel element found');
                return;
            }
            
            var carouselId = $carousel.attr('class').split(' ').find(cls => cls.includes('carousel-'));
            console.log('Carousel ID:', carouselId);
            
            // Analyze carousel container
            this.analyzeElement($carousel[0], 'Carousel Container');
            
            // Analyze slick elements if initialized
            if ($carousel.hasClass('slick-initialized')) {
                var $track = $carousel.find('.slick-track');
                var $slides = $carousel.find('.slick-slide');
                
                if ($track.length > 0) {
                    this.analyzeElement($track[0], 'Slick Track');
                }
                
                if ($slides.length > 0) {
                    this.analyzeElement($slides[0], 'First Slide');
                    
                    // Check if slides are positioned correctly
                    this.analyzeSlidePositioning($slides);
                }
            } else {
                // Analyze raw items
                var $items = $carousel.find('> div');
                if ($items.length > 0) {
                    this.analyzeElement($items[0], 'First Raw Item');
                    this.analyzeRawItemPositioning($items);
                }
            }
        },
        
        analyzeElement: function(element, label) {
            var computed = window.getComputedStyle(element);
            
            console.log('\n' + label + ':');
            console.log('  display:', computed.display);
            console.log('  position:', computed.position);
            console.log('  float:', computed.float);
            console.log('  visibility:', computed.visibility);
            console.log('  opacity:', computed.opacity);
            console.log('  transform:', computed.transform);
            console.log('  width:', computed.width);
            console.log('  height:', computed.height);
            console.log('  overflow:', computed.overflow);
            
            // Check for problematic values
            var issues = [];
            
            if (computed.display === 'none') {
                issues.push('❌ Element is hidden (display: none)');
            }
            
            if (computed.visibility === 'hidden') {
                issues.push('❌ Element is hidden (visibility: hidden)');
            }
            
            if (computed.opacity === '0') {
                issues.push('❌ Element is transparent (opacity: 0)');
            }
            
            if (computed.float !== 'none') {
                issues.push('⚠️ Element is floated (' + computed.float + ')');
            }
            
            if (computed.position === 'absolute' && label.includes('Slide')) {
                issues.push('⚠️ Slide has absolute positioning');
            }
            
            if (issues.length > 0) {
                console.log('  Issues:');
                issues.forEach(issue => console.log('    ' + issue));
            } else {
                console.log('  ✅ No obvious display issues');
            }
        },
        
        analyzeSlidePositioning: function($slides) {
            console.log('\nSlide Positioning Analysis:');
            
            var positions = [];
            $slides.each(function(index) {
                var rect = this.getBoundingClientRect();
                positions.push({
                    index: index,
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height
                });
            });
            
            if (positions.length < 2) {
                console.log('  Not enough slides to analyze positioning');
                return;
            }
            
            // Check if slides are horizontal or vertical
            var firstSlide = positions[0];
            var secondSlide = positions[1];
            
            var horizontalGap = Math.abs(secondSlide.left - firstSlide.left);
            var verticalGap = Math.abs(secondSlide.top - firstSlide.top);
            
            console.log('  First slide position:', firstSlide.left + ', ' + firstSlide.top);
            console.log('  Second slide position:', secondSlide.left + ', ' + secondSlide.top);
            console.log('  Horizontal gap:', horizontalGap + 'px');
            console.log('  Vertical gap:', verticalGap + 'px');
            
            if (verticalGap > horizontalGap && verticalGap > 10) {
                console.log('  ❌ PROBLEM: Slides are stacked vertically');
                console.log('  Expected: Slides should be positioned horizontally');
            } else if (horizontalGap > 10) {
                console.log('  ✅ Slides are positioned horizontally');
            } else {
                console.log('  ⚠️ Slides appear to be overlapping');
            }
        },
        
        analyzeRawItemPositioning: function($items) {
            console.log('\nRaw Item Positioning Analysis:');
            
            if ($items.length < 2) {
                console.log('  Not enough items to analyze positioning');
                return;
            }
            
            var firstRect = $items[0].getBoundingClientRect();
            var secondRect = $items[1].getBoundingClientRect();
            
            var horizontalGap = Math.abs(secondRect.left - firstRect.left);
            var verticalGap = Math.abs(secondRect.top - firstRect.top);
            
            console.log('  First item position:', firstRect.left + ', ' + firstRect.top);
            console.log('  Second item position:', secondRect.left + ', ' + secondRect.top);
            console.log('  Horizontal gap:', horizontalGap + 'px');
            console.log('  Vertical gap:', verticalGap + 'px');
            
            if (verticalGap > horizontalGap && verticalGap > 10) {
                console.log('  ❌ PROBLEM: Items are stacked vertically (carousel not working)');
            } else {
                console.log('  ✅ Items are positioned horizontally');
            }
        },
        
        findConflictingRules: function(element) {
            // This would require more complex analysis of CSS rules
            // For now, we'll focus on computed styles
            console.log('CSS rule analysis not yet implemented');
        },
        
        generateFix: function() {
            console.log('\n=== SUGGESTED FIXES ===');
            
            var $carouselBlocks = $('.carousel-block');
            
            if ($carouselBlocks.length === 0) {
                console.log('No carousel blocks to fix');
                return;
            }
            
            var fixes = [];
            
            $carouselBlocks.each(function(index) {
                var $carousel = $(this).find('[class*="carousel-"]');
                
                if ($carousel.length === 0) return;
                
                var carouselId = $carousel.attr('class').split(' ').find(cls => cls.includes('carousel-'));
                
                if (!$carousel.hasClass('slick-initialized')) {
                    fixes.push('.' + carouselId + ' { display: block !important; }');
                    fixes.push('.' + carouselId + ' > div { display: block !important; float: none !important; }');
                } else {
                    var $slides = $carousel.find('.slick-slide');
                    if ($slides.length > 0) {
                        var computed = window.getComputedStyle($slides[0]);
                        
                        if (computed.display !== 'flex') {
                            fixes.push('.' + carouselId + ' .slick-slide { display: flex !important; }');
                        }
                        
                        if (computed.float !== 'none') {
                            fixes.push('.' + carouselId + ' .slick-slide { float: none !important; }');
                        }
                    }
                }
            });
            
            if (fixes.length > 0) {
                console.log('Apply these CSS fixes:');
                fixes.forEach(fix => console.log('  ' + fix));
            } else {
                console.log('No specific fixes identified');
            }
        }
    };
    
    // Auto-run if carousel_debug parameter is present
    $(document).ready(function() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('carousel_debug')) {
            setTimeout(function() {
                window.CarouselStyleAnalyzer.analyze();
                window.CarouselStyleAnalyzer.generateFix();
            }, 2000);
        }
    });

    } // end initializeAnalyzer

})();

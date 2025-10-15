/**
 * Carousel Debug Bookmarklet
 * 
 * This script can be run as a bookmarklet on any page to diagnose carousel issues.
 * To use: Copy this code and create a bookmarklet, or run in browser console.
 */

javascript:(function() {
    
    // Create debug overlay
    var debugOverlay = document.createElement('div');
    debugOverlay.style.cssText = `
        position: fixed;
        top: 10px;
        right: 10px;
        width: 400px;
        max-height: 80vh;
        background: rgba(0,0,0,0.9);
        color: white;
        padding: 15px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 12px;
        z-index: 99999;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    `;
    
    var debugContent = '<h3 style="margin:0 0 10px 0; color: #4CAF50;">🔍 Carousel Debug</h3>';
    
    function addDebugLine(message, type = 'info') {
        var color = type === 'error' ? '#f44336' : type === 'warning' ? '#ff9800' : type === 'success' ? '#4CAF50' : '#fff';
        debugContent += '<div style="color: ' + color + '; margin: 2px 0;">' + message + '</div>';
    }
    
    // Check jQuery
    if (typeof jQuery === 'undefined') {
        addDebugLine('❌ jQuery not available', 'error');
    } else {
        addDebugLine('✅ jQuery v' + jQuery.fn.jquery, 'success');
        
        // Check Slick
        if (typeof jQuery.fn.slick === 'undefined') {
            addDebugLine('❌ Slick carousel not available', 'error');
        } else {
            addDebugLine('✅ Slick carousel available', 'success');
        }
    }
    
    // Find carousel blocks
    var carouselBlocks = document.querySelectorAll('.carousel-block');
    addDebugLine('📊 Found ' + carouselBlocks.length + ' carousel block(s)');
    
    if (carouselBlocks.length === 0) {
        addDebugLine('ℹ️ No carousel blocks on this page', 'warning');
    } else {
        
        carouselBlocks.forEach(function(block, index) {
            addDebugLine('--- Carousel ' + (index + 1) + ' ---');
            
            var carousel = block.querySelector('[class*="carousel-"]');
            if (!carousel) {
                addDebugLine('⚠️ No carousel element found', 'warning');
                return;
            }
            
            var carouselClass = Array.from(carousel.classList).find(cls => cls.includes('carousel-'));
            addDebugLine('🎠 Class: ' + carouselClass);
            
            // Check initialization
            var isInitialized = carousel.classList.contains('slick-initialized');
            addDebugLine('🔧 Initialized: ' + (isInitialized ? '✅ Yes' : '❌ No'), isInitialized ? 'success' : 'error');
            
            if (isInitialized) {
                var slides = carousel.querySelectorAll('.slick-slide');
                var visibleSlides = Array.from(slides).filter(slide => {
                    var style = window.getComputedStyle(slide);
                    return style.display !== 'none' && style.visibility !== 'hidden';
                });
                
                addDebugLine('📄 Total slides: ' + slides.length);
                addDebugLine('👁️ Visible slides: ' + visibleSlides.length);
                
                // Check navigation
                var arrows = carousel.querySelectorAll('.slick-arrow');
                var dots = carousel.querySelectorAll('.slick-dots');
                addDebugLine('🏹 Arrows: ' + arrows.length);
                addDebugLine('🔘 Dots: ' + dots.length);
                
                // Check slide display properties
                if (slides.length > 0) {
                    var firstSlide = slides[0];
                    var computedStyle = window.getComputedStyle(firstSlide);
                    addDebugLine('🎨 First slide display: ' + computedStyle.display);
                    addDebugLine('🎨 First slide position: ' + computedStyle.position);
                    addDebugLine('🎨 First slide float: ' + computedStyle.float);
                }
                
            } else {
                // Check raw items
                var items = carousel.querySelectorAll('> div');
                addDebugLine('📦 Raw items: ' + items.length);
                
                if (items.length > 1) {
                    var firstRect = items[0].getBoundingClientRect();
                    var secondRect = items[1].getBoundingClientRect();
                    var isVertical = Math.abs(firstRect.top - secondRect.top) > 10;
                    addDebugLine('📐 Layout: ' + (isVertical ? '⬇️ Vertical (PROBLEM)' : '➡️ Horizontal'), isVertical ? 'error' : 'success');
                }
            }
            
            // Check CSS properties
            var carouselStyle = window.getComputedStyle(carousel);
            addDebugLine('🎨 Carousel display: ' + carouselStyle.display);
            addDebugLine('🎨 Carousel position: ' + carouselStyle.position);
            addDebugLine('🎨 Carousel overflow: ' + carouselStyle.overflow);
        });
    }
    
    // Check CSS loading
    addDebugLine('--- CSS Analysis ---');
    var stylesheets = Array.from(document.styleSheets);
    var libraryThemeCSS = stylesheets.some(sheet => sheet.href && sheet.href.includes('library'));
    var slickCSS = stylesheets.some(sheet => sheet.href && sheet.href.includes('slick'));
    var carouselFixCSS = stylesheets.some(sheet => sheet.href && sheet.href.includes('carousel-fix'));
    
    addDebugLine('📄 LibraryTheme CSS: ' + (libraryThemeCSS ? '✅' : '❌'), libraryThemeCSS ? 'success' : 'error');
    addDebugLine('📄 Slick CSS: ' + (slickCSS ? '✅' : '❌'), slickCSS ? 'success' : 'error');
    addDebugLine('📄 Carousel Fix CSS: ' + (carouselFixCSS ? '✅' : '❌'), carouselFixCSS ? 'success' : 'error');
    
    // Check JavaScript loading
    addDebugLine('--- JS Analysis ---');
    var scripts = Array.from(document.scripts);
    var carouselFixJS = scripts.some(script => script.src && script.src.includes('carousel-fix'));
    var carouselDiagnosticJS = scripts.some(script => script.src && script.src.includes('carousel-diagnostic'));
    
    addDebugLine('📄 Carousel Fix JS: ' + (carouselFixJS ? '✅' : '❌'), carouselFixJS ? 'success' : 'error');
    addDebugLine('📄 Carousel Diagnostic JS: ' + (carouselDiagnosticJS ? '✅' : '❌'), carouselDiagnosticJS ? 'success' : 'error');
    
    // Add close button
    debugContent += '<div style="margin-top: 15px; text-align: center;"><button onclick="this.parentElement.parentElement.remove()" style="background: #f44336; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Close</button></div>';
    
    debugOverlay.innerHTML = debugContent;
    document.body.appendChild(debugOverlay);
    
    // Also log to console
    console.log('=== CAROUSEL DEBUG COMPLETE ===');
    console.log('Check the debug overlay in the top-right corner for detailed information.');
    
})();

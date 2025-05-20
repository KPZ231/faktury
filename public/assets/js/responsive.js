/**
 * Responsive.js - Handles responsive behavior for the Faktury system
 * This script manages mobile navigation, responsive tables, and other mobile-specific features
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize mobile enhancements
    initMobileNavigation();
    initResponsiveTables();
    initMobileFormImprovements();
    handleOrientationChanges();
    
    // Add viewport-specific classes to body
    setViewportClass();
    window.addEventListener('resize', setViewportClass);
});

/**
 * Initialize mobile-friendly navigation
 */
function initMobileNavigation() {
    // Don't add hamburger menu on login page
    if (document.querySelector('.body_login')) {
        return;
    }
    
    // Check if we need to create a mobile menu - use threshold consistent with CSS media queries
    // This will apply to both mobile (<= 768px) and tablet (769px - 1024px) views
    if (window.innerWidth <= 1024) {
        // If hamburger button already exists, don't add a new one
        if (document.querySelector('.mobile-menu-btn')) {
            return;
        }
        
        // Create hamburger button
        const hamburgerBtn = document.createElement('button');
        hamburgerBtn.classList.add('mobile-menu-btn');
        hamburgerBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
        hamburgerBtn.setAttribute('aria-label', 'Open menu');
        document.body.appendChild(hamburgerBtn);
        
        // Create mobile navigation container
        const mobileNav = document.createElement('nav');
        mobileNav.classList.add('mobile-nav');
        document.body.appendChild(mobileNav);
        
        // Create close button
        const closeBtn = document.createElement('button');
        closeBtn.classList.add('mobile-menu-close');
        closeBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
        closeBtn.setAttribute('aria-label', 'Close menu');
        mobileNav.appendChild(closeBtn);
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.classList.add('mobile-nav-overlay');
        document.body.appendChild(overlay);
        
        // Find existing navigation and clone items
        const existingNav = document.querySelector('.cleannav');
        if (existingNav) {
            const existingList = existingNav.querySelector('.cleannav__list');
            
            if (existingList) {
                // Create new navigation list
                const mobileNavList = document.createElement('ul');
                mobileNavList.classList.add('mobile-nav-list');
                mobileNav.appendChild(mobileNavList);
                
                // Clone navigation items
                const items = existingList.querySelectorAll('.cleannav__item');
                items.forEach(item => {
                    const link = item.querySelector('a');
                    if (link) {
                        const icon = link.querySelector('i');
                        const tooltip = link.getAttribute('data-tooltip');
                        
                        // Create new list item
                        const newItem = document.createElement('li');
                        newItem.classList.add('mobile-nav-item');
                        
                        // Create new link
                        const newLink = document.createElement('a');
                        newLink.classList.add('mobile-nav-link');
                        newLink.href = link.href;
                        
                        // Set active class if original link is active
                        if (link.classList.contains('active')) {
                            newLink.classList.add('active');
                        }
                        
                        // Create icon
                        const newIcon = document.createElement('i');
                        newIcon.className = icon ? icon.className.replace('cleannav__icon', 'mobile-nav-icon') : 'fa-solid fa-circle mobile-nav-icon';
                        
                        // Create text 
                        const text = document.createElement('span');
                        text.textContent = tooltip || 'Menu Item';
                        
                        // Assemble the elements
                        newLink.appendChild(newIcon);
                        newLink.appendChild(text);
                        newItem.appendChild(newLink);
                        mobileNavList.appendChild(newItem);
                    }
                });
            }
        }
        
        // Adjust button position for devices with notches or punch-holes
        if (isNotchedDevice()) {
            hamburgerBtn.style.top = 'calc(10px + env(safe-area-inset-top))';
            mobileNav.style.paddingTop = 'calc(60px + env(safe-area-inset-top))';
        }
        
        // Event listeners for hamburger menu
        hamburgerBtn.addEventListener('click', function() {
            mobileNav.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });
        
        // Close menu on button click
        closeBtn.addEventListener('click', function() {
            mobileNav.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
        });
        
        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            mobileNav.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
        });
        
        // Close menu when clicking on a link (navigate)
        const navLinks = mobileNav.querySelectorAll('.mobile-nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Small delay to allow the click to register before closing
                setTimeout(() => {
                    mobileNav.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = ''; // Restore scrolling
                }, 100);
            });
        });
        
        // Handle device orientation change
        window.addEventListener('orientationchange', function() {
            // Close menu if it's open
            if (mobileNav.classList.contains('active')) {
                mobileNav.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = ''; // Restore scrolling
            }
            
            // Adjust button position after orientation change
            setTimeout(() => {
                if (isNotchedDevice()) {
                    hamburgerBtn.style.top = 'calc(10px + env(safe-area-inset-top))';
                    mobileNav.style.paddingTop = 'calc(60px + env(safe-area-inset-top))';
                }
            }, 300);
        });
    }
    
    // Hide any existing navigation toggles
    const navToggle = document.querySelector('.nav-toggle');
    if (navToggle) {
        navToggle.style.display = 'none';
    }
}

/**
 * Detect devices with notches or punch-holes (iPhone X and newer, many Android phones)
 */
function isNotchedDevice() {
    // Check for iOS devices with notch
    const iOSWithNotch = CSS.supports('padding-top: env(safe-area-inset-top)');
    
    // Check for typical screen dimensions of notched devices
    const isCommonNotchedSize = (
        // iPhone X, XS, 11 Pro
        (window.screen.width === 375 && window.screen.height === 812) ||
        // iPhone XR, XS Max, 11, 11 Pro Max
        (window.screen.width === 414 && window.screen.height === 896) ||
        // iPhone 12 mini
        (window.screen.width === 360 && window.screen.height === 780) ||
        // iPhone 12, 12 Pro
        (window.screen.width === 390 && window.screen.height === 844) ||
        // iPhone 12 Pro Max
        (window.screen.width === 428 && window.screen.height === 926) ||
        // iPhone 13 models (same as 12)
        (window.screen.width === 390 && window.screen.height === 844) ||
        // iPhone 13 Pro Max, 14 Plus
        (window.screen.width === 428 && window.screen.height === 926) ||
        // iPhone 14 Pro
        (window.screen.width === 393 && window.screen.height === 852) ||
        // iPhone 14 Pro Max
        (window.screen.width === 430 && window.screen.height === 932)
    );
    
    return iOSWithNotch || isCommonNotchedSize;
}

/**
 * Initialize responsive table behaviors
 */
function initResponsiveTables() {
    // Add data-print-date attribute to table containers for print styling
    const tables = document.querySelectorAll('.table-container');
    const now = new Date();
    const formattedDate = now.toLocaleDateString('pl-PL') + ' ' + now.toLocaleTimeString('pl-PL');
    
    tables.forEach(table => {
        table.setAttribute('data-print-date', formattedDate);
    });
    
    // Fix z-index issues for all tables - critical for the first row issue on mobile
    tables.forEach(tableContainer => {
        // Ensure container has proper z-index
        tableContainer.style.position = 'relative';
        tableContainer.style.zIndex = '1';
        
        // Find the actual table, tbody and first row
        const table = tableContainer.querySelector('table');
        if (table) {
            // Ensure table has proper structure with thead and tbody
            if (!table.querySelector('thead') && table.rows.length > 0) {
                const thead = document.createElement('thead');
                const headerRow = table.rows[0];
                thead.appendChild(headerRow.cloneNode(true));
                table.insertBefore(thead, table.firstChild);
                table.deleteRow(0);
                
                // If there's no tbody, create one and move all remaining rows into it
                if (!table.querySelector('tbody')) {
                    const tbody = document.createElement('tbody');
                    while (table.rows.length > 0) {
                        tbody.appendChild(table.rows[0]);
                    }
                    table.appendChild(tbody);
                }
            }
            
            // Fix table positioning
            table.style.position = 'relative';
            table.style.zIndex = '1';
            
            const tbody = table.querySelector('tbody');
            if (tbody) {
                tbody.style.position = 'relative';
                tbody.style.zIndex = '1';
                
                // Find all rows and ensure proper positioning
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    row.style.position = 'relative';
                    row.style.zIndex = '1';
                });
            }
            
            // Fix header positioning
            const thead = table.querySelector('thead');
            if (thead) {
                thead.style.position = 'sticky';
                thead.style.top = '0';
                thead.style.zIndex = '10';
                
                // Ensure proper background color for headers
                const headerCells = thead.querySelectorAll('th');
                headerCells.forEach(cell => {
                    cell.style.position = 'sticky';
                    cell.style.backgroundColor = cell.style.backgroundColor || '#64b5f6';
                    cell.style.color = 'white';
                    cell.style.zIndex = '2';
                });
            }
        }
    });
    
    // Add double-tap functionality for mobile to show additional information
    const tableCells = document.querySelectorAll('td:not(.action-cell)');
    
    tableCells.forEach(cell => {
        cell.addEventListener('click', function() {
            // Add a tap highlight class to the cell
            if (window.innerWidth <= 768) {
                if (this.classList.contains('tapped')) {
                    this.classList.remove('tapped');
                } else {
                    // Remove any existing tapped class
                    document.querySelectorAll('.tapped').forEach(el => {
                        el.classList.remove('tapped');
                    });
                    
                    this.classList.add('tapped');
                    
                    // Style for tapped state
                    this.style.backgroundColor = 'rgba(33, 150, 243, 0.1)';
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        this.classList.remove('tapped');
                        this.style.backgroundColor = '';
                    }, 3000);
                }
            }
        });
    });
    
    // Fix table layout for mobile viewing
    if (window.innerWidth <= 992) {
        // Make sure tables are properly contained
        tables.forEach(tableContainer => {
            // Ensure container has proper styles
            tableContainer.style.width = '100%';
            tableContainer.style.margin = '10px 0';
            tableContainer.style.overflowX = 'auto';
            tableContainer.style.WebkitOverflowScrolling = 'touch';
            
            // Find actual table element
            const tableEl = tableContainer.querySelector('table');
            if (tableEl) {
                // For mobile devices, use max-content width to ensure proper scrolling
                if (window.innerWidth <= 768) {
                    tableEl.style.width = 'max-content';
                    tableEl.style.minWidth = '100%';
                } else {
                    // For tablets, prefer full width when possible
                    tableEl.style.width = '100%';
                    tableEl.style.minWidth = '100%';
                }
                
                // Better cell styling for all device sizes
                const cells = tableEl.querySelectorAll('th, td');
                cells.forEach(cell => {
                    cell.style.whiteSpace = 'nowrap';
                    cell.style.overflow = 'hidden';
                    cell.style.textOverflow = 'ellipsis';
                    
                    // Adjust max width based on device size
                    if (window.innerWidth <= 480) {
                        cell.style.maxWidth = '120px'; // Small phones
                    } else if (window.innerWidth <= 768) {
                        cell.style.maxWidth = '150px'; // Regular phones
                    } else {
                        cell.style.maxWidth = '200px'; // Tablets
                    }
                });
            }
        });
        
        // Add swipe indicator for mobile and tablet
        if (window.innerWidth <= 1024) {
            tables.forEach(tableContainer => {
                // Check if the table needs horizontal scrolling
                if (tableContainer.scrollWidth > tableContainer.clientWidth) {
                    // Check if swipe indicator already exists
                    const existingSwipeIndicator = tableContainer.previousElementSibling?.classList?.contains('swipe-indicator');
                    if (!existingSwipeIndicator) {
                        const swipeIndicator = document.createElement('div');
                        swipeIndicator.className = 'swipe-indicator';
                        swipeIndicator.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Przesuń w bok <i class="fa-solid fa-arrow-right"></i>';
                        swipeIndicator.style.textAlign = 'center';
                        swipeIndicator.style.padding = '8px';
                        swipeIndicator.style.backgroundColor = 'rgba(33, 150, 243, 0.1)';
                        swipeIndicator.style.borderRadius = '5px';
                        swipeIndicator.style.marginBottom = '10px';
                        swipeIndicator.style.fontSize = '14px';
                        swipeIndicator.style.color = '#0277bd';
                        swipeIndicator.style.fontWeight = '500';
                        swipeIndicator.style.border = '1px dashed #90caf9';
                        
                        // Insert the swipe indicator before the table container
                        tableContainer.parentNode.insertBefore(swipeIndicator, tableContainer);
                        
                        // Auto-hide after 5 seconds
                        setTimeout(() => {
                            swipeIndicator.style.opacity = '0';
                            swipeIndicator.style.transition = 'opacity 0.5s ease';
                            
                            // Remove from DOM after fade-out
                            setTimeout(() => {
                                swipeIndicator.remove();
                            }, 500);
                        }, 5000);
                    }
                }
            });
            
            return; // Skip adding scroll indicators on mobile
        }
        
        // Tablet-specific enhancements
        if (window.innerWidth <= 1024 && window.innerWidth > 768) {
            // Add subtle scroll indicators for tablets
            tables.forEach(tableContainer => {
                // Only add if the table is wider than its container
                if (tableContainer.scrollWidth > tableContainer.clientWidth) {
                    // Check if indicators already exist
                    const existingLeftIndicator = tableContainer.querySelector('.scroll-indicator-left');
                    const existingRightIndicator = tableContainer.querySelector('.scroll-indicator-right');
                    
                    if (!existingLeftIndicator && !existingRightIndicator) {
                        // Add scroll indicators
                        const leftIndicator = document.createElement('div');
                        const rightIndicator = document.createElement('div');
                        
                        leftIndicator.className = 'scroll-indicator-left';
                        rightIndicator.className = 'scroll-indicator-right';
                        
                        // Style indicators for tablets - more subtle than desktop
                        const indicatorStyle = {
                            position: 'absolute',
                            top: '50%',
                            width: '20px',
                            height: '40px',
                            backgroundColor: 'rgba(0, 0, 0, 0.15)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            borderRadius: '3px',
                            color: 'white',
                            zIndex: '5',
                            transform: 'translateY(-50%)',
                            pointerEvents: 'none',
                            transition: 'opacity 0.2s ease'
                        };
                        
                        Object.assign(leftIndicator.style, indicatorStyle);
                        Object.assign(rightIndicator.style, indicatorStyle);
                        
                        leftIndicator.style.left = '0';
                        leftIndicator.style.borderTopLeftRadius = '0';
                        leftIndicator.style.borderBottomLeftRadius = '0';
                        leftIndicator.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                        
                        rightIndicator.style.right = '0';
                        rightIndicator.style.borderTopRightRadius = '0';
                        rightIndicator.style.borderBottomRightRadius = '0';
                        rightIndicator.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                        
                        tableContainer.appendChild(leftIndicator);
                        tableContainer.appendChild(rightIndicator);
                        
                        // Show/hide indicators based on scroll position
                        updateScrollIndicators(tableContainer, leftIndicator, rightIndicator);
                        
                        tableContainer.addEventListener('scroll', function() {
                            updateScrollIndicators(this, leftIndicator, rightIndicator);
                        });
                    }
                }
            });
            
            return; // Skip desktop indicators for tablets
        }
    }
    
    // Desktop behavior - horizontal scroll indicators
    tables.forEach(tableContainer => {
        // Only add if the table is wider than its container
        if (tableContainer.scrollWidth > tableContainer.clientWidth) {
            // Check if indicators already exist
            const existingLeftIndicator = tableContainer.querySelector('.scroll-indicator-left');
            const existingRightIndicator = tableContainer.querySelector('.scroll-indicator-right');
            
            if (!existingLeftIndicator && !existingRightIndicator) {
                // Add scroll indicators
                const leftIndicator = document.createElement('div');
                const rightIndicator = document.createElement('div');
                
                leftIndicator.className = 'scroll-indicator-left';
                rightIndicator.className = 'scroll-indicator-right';
                
                // Style indicators
                const indicatorStyle = {
                    position: 'absolute',
                    top: '50%',
                    width: '30px',
                    height: '50px',
                    backgroundColor: 'rgba(0, 0, 0, 0.2)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    borderRadius: '5px',
                    color: 'white',
                    zIndex: '5',
                    transform: 'translateY(-50%)',
                    pointerEvents: 'none'
                };
                
                Object.assign(leftIndicator.style, indicatorStyle);
                Object.assign(rightIndicator.style, indicatorStyle);
                
                leftIndicator.style.left = '0';
                leftIndicator.style.borderTopLeftRadius = '0';
                leftIndicator.style.borderBottomLeftRadius = '0';
                leftIndicator.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                
                rightIndicator.style.right = '0';
                rightIndicator.style.borderTopRightRadius = '0';
                rightIndicator.style.borderBottomRightRadius = '0';
                rightIndicator.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                
                tableContainer.appendChild(leftIndicator);
                tableContainer.appendChild(rightIndicator);
                
                // Show/hide indicators based on scroll position
                updateScrollIndicators(tableContainer, leftIndicator, rightIndicator);
                
                tableContainer.addEventListener('scroll', function() {
                    updateScrollIndicators(this, leftIndicator, rightIndicator);
                });
            }
        }
    });
}

/**
 * Update scroll indicators visibility based on scroll position
 */
function updateScrollIndicators(container, leftIndicator, rightIndicator) {
    const scrollLeft = container.scrollLeft;
    const maxScrollLeft = container.scrollWidth - container.clientWidth;
    
    // Show/hide left indicator
    if (scrollLeft <= 5) {
        leftIndicator.style.opacity = '0';
    } else {
        leftIndicator.style.opacity = '0.7';
    }
    
    // Show/hide right indicator
    if (scrollLeft >= maxScrollLeft - 5) {
        rightIndicator.style.opacity = '0';
    } else {
        rightIndicator.style.opacity = '0.7';
    }
}

/**
 * Initialize mobile form improvements
 */
function initMobileFormImprovements() {
    // Prevent zoom on focus for iOS devices
    const inputs = document.querySelectorAll('input[type="text"], input[type="number"], input[type="email"], input[type="password"]');
    
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            // Force font-size to 16px to prevent iOS zoom
            this.style.fontSize = '16px';
        });
        
        input.addEventListener('blur', function() {
            this.style.fontSize = ''; // Reset to CSS value
        });
    });
    
    // Add "Back to Top" button for long mobile pages
    if (document.body.scrollHeight > window.innerHeight * 2) {
        const backToTopBtn = document.createElement('button');
        backToTopBtn.classList.add('back-to-top');
        backToTopBtn.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
        backToTopBtn.style.position = 'fixed';
        backToTopBtn.style.bottom = '20px';
        backToTopBtn.style.right = '20px';
        backToTopBtn.style.width = '40px';
        backToTopBtn.style.height = '40px';
        backToTopBtn.style.borderRadius = '50%';
        backToTopBtn.style.backgroundColor = 'var(--primary-color, #64b5f6)';
        backToTopBtn.style.color = 'white';
        backToTopBtn.style.border = 'none';
        backToTopBtn.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.2)';
        backToTopBtn.style.cursor = 'pointer';
        backToTopBtn.style.display = 'none';
        backToTopBtn.style.zIndex = '999';
        document.body.appendChild(backToTopBtn);
        
        // Show/hide button based on scroll position
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.style.display = 'block';
            } else {
                backToTopBtn.style.display = 'none';
            }
        });
        
        // Scroll to top when clicked
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Make modals more mobile-friendly
    const modals = document.querySelectorAll('.modal-content');
    
    modals.forEach(modal => {
        if (window.innerWidth <= 768) {
            // Add close button for mobile
            const closeModalBtn = document.createElement('button');
            closeModalBtn.classList.add('mobile-modal-close');
            closeModalBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
            closeModalBtn.style.position = 'absolute';
            closeModalBtn.style.top = '10px';
            closeModalBtn.style.right = '10px';
            closeModalBtn.style.background = 'none';
            closeModalBtn.style.border = 'none';
            closeModalBtn.style.fontSize = '24px';
            closeModalBtn.style.color = '#aaa';
            closeModalBtn.style.cursor = 'pointer';
            closeModalBtn.style.zIndex = '1003';
            
            modal.style.position = 'relative';
            modal.appendChild(closeModalBtn);
            
            // Close the modal when the button is clicked
            closeModalBtn.addEventListener('click', function() {
                const modalContainer = this.closest('.modal');
                if (modalContainer) {
                    modalContainer.style.display = 'none';
                }
            });
        }
    });
}

/**
 * Handle orientation changes for mobile devices
 */
function handleOrientationChanges() {
    // Listen for orientation changes
    window.addEventListener('orientationchange', function() {
        // Fix viewport issues on orientation change
        setTimeout(function() {
            // Reset scroll position
            window.scrollTo(0, 1);
            
            // Update viewport class
            setViewportClass();
            
            // Fix table headers and layout on orientation change
            const tables = document.querySelectorAll('.table-container');
            tables.forEach(tableContainer => {
                // Re-adjust the table container
                tableContainer.style.width = '100%';
                tableContainer.style.overflowX = 'auto';
                tableContainer.style.WebkitOverflowScrolling = 'touch';
                
                // Find the table element
                const table = tableContainer.querySelector('table');
                if (table) {
                    // Fix z-index and positioning for the table
                    table.style.position = 'relative';
                    table.style.zIndex = '1';
                    
                    // Different table width settings for mobile vs tablet
                    if (window.innerWidth <= 768) {
                        table.style.width = 'max-content';
                        table.style.minWidth = '100%';
                    } else {
                        table.style.width = '100%';
                        table.style.minWidth = '100%';
                    }
                    
                    // Find and fix the tbody positioning
                    const tbody = table.querySelector('tbody');
                    if (tbody) {
                        tbody.style.position = 'relative';
                        tbody.style.zIndex = '1';
                        
                        // Adjust all rows
                        const rows = tbody.querySelectorAll('tr');
                        rows.forEach(row => {
                            row.style.position = 'relative';
                            row.style.zIndex = '1';
                        });
                    }
                    
                    // Refresh table header positioning for the orientation
                    const thead = table.querySelector('thead');
                    if (thead) {
                        // Adjust header based on device width (different for phone vs tablet)
                        thead.style.position = 'sticky';
                        thead.style.top = window.innerWidth <= 768 ? '60px' : '0';
                        thead.style.zIndex = '10';
                        
                        // Ensure background colors for all header cells
                        const headerCells = thead.querySelectorAll('th');
                        headerCells.forEach(cell => {
                            cell.style.position = 'sticky';
                            cell.style.backgroundColor = cell.style.backgroundColor || '#f8f9fa';
                            cell.style.zIndex = '2';
                        });
                    }
                    
                    // Adjust cell widths based on screen size
                    const cells = table.querySelectorAll('th, td');
                    cells.forEach(cell => {
                        // Clear existing width/styles
                        cell.style.whiteSpace = 'nowrap';
                        cell.style.overflow = 'hidden';
                        cell.style.textOverflow = 'ellipsis';
                        
                        // Apply width based on device size
                        if (window.innerWidth <= 480) {
                            cell.style.maxWidth = '120px'; // Small phones
                        } else if (window.innerWidth <= 768) {
                            cell.style.maxWidth = '150px'; // Regular phones
                        } else {
                            cell.style.maxWidth = '200px'; // Tablets and larger
                        }
                    });
                }
                
                // Check if we need scroll indicators
                if (tableContainer.scrollWidth > tableContainer.clientWidth) {
                    // Remove any existing indicators first
                    const oldIndicators = tableContainer.querySelectorAll('.scroll-indicator-left, .scroll-indicator-right');
                    oldIndicators.forEach(indicator => indicator.remove());
                    
                    // Remove any existing swipe indicators
                    const oldSwipeIndicator = tableContainer.previousElementSibling;
                    if (oldSwipeIndicator && oldSwipeIndicator.classList.contains('swipe-indicator')) {
                        oldSwipeIndicator.remove();
                    }
                    
                    // For mobile, add swipe indicator instead of arrows
                    if (window.innerWidth <= 768) {
                        const swipeIndicator = document.createElement('div');
                        swipeIndicator.className = 'swipe-indicator';
                        swipeIndicator.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Przesuń w bok <i class="fa-solid fa-arrow-right"></i>';
                        swipeIndicator.style.textAlign = 'center';
                        swipeIndicator.style.padding = '8px';
                        swipeIndicator.style.backgroundColor = 'rgba(33, 150, 243, 0.1)';
                        swipeIndicator.style.borderRadius = '5px';
                        swipeIndicator.style.marginBottom = '10px';
                        swipeIndicator.style.fontSize = '14px';
                        swipeIndicator.style.color = '#0277bd';
                        swipeIndicator.style.fontWeight = '500';
                        swipeIndicator.style.border = '1px dashed #90caf9';
                        
                        // Insert the swipe indicator before the table container
                        tableContainer.parentNode.insertBefore(swipeIndicator, tableContainer);
                        
                        // Auto-hide after 5 seconds
                        setTimeout(() => {
                            swipeIndicator.style.opacity = '0';
                            swipeIndicator.style.transition = 'opacity 0.5s ease';
                            
                            // Remove from DOM after fade-out
                            setTimeout(() => {
                                swipeIndicator.remove();
                            }, 500);
                        }, 5000);
                    } 
                    // For tablets, add more subtle indicators
                    else if (window.innerWidth <= 992) {
                        // Add scroll indicators for tablets
                        const leftIndicator = document.createElement('div');
                        const rightIndicator = document.createElement('div');
                        
                        leftIndicator.className = 'scroll-indicator-left';
                        rightIndicator.className = 'scroll-indicator-right';
                        
                        // Style indicators for tablets
                        const indicatorStyle = {
                            position: 'absolute',
                            top: '50%',
                            width: '20px',
                            height: '40px',
                            backgroundColor: 'rgba(0, 0, 0, 0.15)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            borderRadius: '3px',
                            color: 'white',
                            zIndex: '5',
                            transform: 'translateY(-50%)',
                            pointerEvents: 'none',
                            transition: 'opacity 0.2s ease'
                        };
                        
                        Object.assign(leftIndicator.style, indicatorStyle);
                        Object.assign(rightIndicator.style, indicatorStyle);
                        
                        leftIndicator.style.left = '0';
                        leftIndicator.style.borderTopLeftRadius = '0';
                        leftIndicator.style.borderBottomLeftRadius = '0';
                        leftIndicator.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                        
                        rightIndicator.style.right = '0';
                        rightIndicator.style.borderTopRightRadius = '0';
                        rightIndicator.style.borderBottomRightRadius = '0';
                        rightIndicator.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                        
                        tableContainer.appendChild(leftIndicator);
                        tableContainer.appendChild(rightIndicator);
                        
                        // Update indicator visibility
                        updateScrollIndicators(tableContainer, leftIndicator, rightIndicator);
                    }
                    // For desktop, add standard indicators
                    else {
                        // Add scroll indicators for desktop
                        const leftIndicator = document.createElement('div');
                        const rightIndicator = document.createElement('div');
                        
                        leftIndicator.className = 'scroll-indicator-left';
                        rightIndicator.className = 'scroll-indicator-right';
                        
                        // Style indicators
                        const indicatorStyle = {
                            position: 'absolute',
                            top: '50%',
                            width: '30px',
                            height: '50px',
                            backgroundColor: 'rgba(0, 0, 0, 0.2)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            borderRadius: '5px',
                            color: 'white',
                            zIndex: '5',
                            transform: 'translateY(-50%)',
                            pointerEvents: 'none'
                        };
                        
                        Object.assign(leftIndicator.style, indicatorStyle);
                        Object.assign(rightIndicator.style, indicatorStyle);
                        
                        leftIndicator.style.left = '0';
                        leftIndicator.style.borderTopLeftRadius = '0';
                        leftIndicator.style.borderBottomLeftRadius = '0';
                        leftIndicator.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                        
                        rightIndicator.style.right = '0';
                        rightIndicator.style.borderTopRightRadius = '0';
                        rightIndicator.style.borderBottomRightRadius = '0';
                        rightIndicator.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                        
                        tableContainer.appendChild(leftIndicator);
                        tableContainer.appendChild(rightIndicator);
                        
                        // Update indicator visibility
                        updateScrollIndicators(tableContainer, leftIndicator, rightIndicator);
                    }
                }
            });
            
            // Fix header for new orientation
            const header = document.querySelector('header');
            if (header) {
                // All devices now have full-width header
                header.style.top = '0';
                header.style.width = '100%';
                header.style.left = '0';
                
                // Adjust height based on device
                if (window.innerWidth <= 768) {
                    header.style.height = 'auto';
                    header.style.minHeight = '60px';
                } else {
                    header.style.height = 'auto';
                    header.style.minHeight = '70px';
                }
            }
            
            // Ensure hamburger menu button is properly positioned after orientation change
            const hamburgerBtn = document.querySelector('.mobile-menu-btn');
            if (hamburgerBtn) {
                if (isNotchedDevice()) {
                    hamburgerBtn.style.top = 'calc(10px + env(safe-area-inset-top))';
                } else {
                    hamburgerBtn.style.top = '10px';
                }
            }
            
            // Close mobile menu if it's open during orientation change
            const mobileNav = document.querySelector('.mobile-nav');
            const overlay = document.querySelector('.mobile-nav-overlay');
            if (mobileNav && mobileNav.classList.contains('active')) {
                mobileNav.classList.remove('active');
                if (overlay) {
                    overlay.classList.remove('active');
                }
                document.body.style.overflow = '';
            }
            
            // Reapply any responsive styles for the current page
            if (document.querySelector('.content-wrapper')) {
                // Special handling for podsumowanie-spraw.php
                document.querySelector('.content-wrapper').style.marginTop = window.innerWidth <= 768 ? '70px' : '80px';
            }
        }, 300);
    });
}

/**
 * Set viewport-specific class on body element
 */
function setViewportClass() {
    const body = document.body;
    
    // Remove existing viewport classes
    body.classList.remove('viewport-desktop', 'viewport-tablet', 'viewport-mobile', 'orientation-portrait', 'orientation-landscape');
    
    // Add appropriate viewport class
    if (window.innerWidth > 1024) {
        body.classList.add('viewport-desktop');
    } else if (window.innerWidth >= 769) {
        body.classList.add('viewport-tablet');
    } else {
        body.classList.add('viewport-mobile');
    }
    
    // Ensure mobile navigation is properly initialized when window is resized
    if (window.innerWidth <= 1024) {
        // If we're on mobile or tablet, make sure hamburger menu is shown
        if (!document.querySelector('.mobile-menu-btn')) {
            initMobileNavigation();
        }
    } else {
        // If we're on desktop, remove hamburger menu if it exists
        const hamburgerBtn = document.querySelector('.mobile-menu-btn');
        const mobileNav = document.querySelector('.mobile-nav');
        const overlay = document.querySelector('.mobile-nav-overlay');
        
        if (hamburgerBtn) hamburgerBtn.remove();
        if (mobileNav) mobileNav.remove();
        if (overlay) overlay.remove();
    }
    
    // Add orientation class
    if (window.innerWidth > window.innerHeight) {
        body.classList.add('orientation-landscape');
    } else {
        body.classList.add('orientation-portrait');
    }
} 

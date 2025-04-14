document.addEventListener('DOMContentLoaded', function() {
    // Handle user menu dropdown
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        userMenu.addEventListener('click', function(e) {
            e.currentTarget.classList.toggle('active');
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (userMenu && !userMenu.contains(e.target)) {
            userMenu.classList.remove('active');
        }
    });

    // Handle mobile navigation
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mainNav = document.querySelector('.main-nav ul');
    
    if (mobileMenuToggle && mainNav) {
        mobileMenuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            this.classList.toggle('active');
        });
    }

    // Add to cart animation
    const addToCartButtons = document.querySelectorAll('.btn-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Add animation class
            this.classList.add('adding');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                this.classList.remove('adding');
                
                // Optional: Show success message
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> Aggiunto';
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 1500);
                
                // Here you would typically make an AJAX call to add the item to cart
                const listingId = this.getAttribute('data-listing-id');
                if (listingId) {
                    addToCart(listingId);
                }
            }, 500);
        });
    });

    // Add to wishlist functionality
    const wishlistButtons = document.querySelectorAll('.btn-wishlist');
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Toggle active class (for color change)
            this.classList.toggle('active');
            
            // Here you would make an AJAX call to toggle wishlist status
            const cardId = this.getAttribute('data-card-id');
            if (cardId) {
                toggleWishlist(cardId);
            }
        });
    });

    // Function to add item to cart via AJAX
    function addToCart(listingId) {
        // Example AJAX call - would be implemented based on your backend
        /*
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'listing_id=' + listingId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count or show notification
            }
        });
        */
    }

    // Function to toggle wishlist status via AJAX
    function toggleWishlist(cardId) {
        // Example AJAX call - would be implemented based on your backend
        /*
        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'card_id=' + cardId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show notification
            }
        });
        */
    }

    // Marketplace filters for mobile
    const filterToggle = document.getElementById('filter-toggle');
    const filtersSidebar = document.querySelector('.filters-sidebar');
    
    if (filterToggle && filtersSidebar) {
        filterToggle.addEventListener('click', function() {
            filtersSidebar.classList.toggle('active');
        });
        
        // Close filters when clicking outside
        document.addEventListener('click', function(e) {
            if (!filtersSidebar.contains(e.target) && e.target !== filterToggle) {
                filtersSidebar.classList.remove('active');
            }
        });
    }

    // Auto-submit filters when changed
    const filterSelects = document.querySelectorAll('.filters-sidebar select:not(#game_id)');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            const filterForm = document.getElementById('filter-form');
            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    // Image lazy loading
    if ('loading' in HTMLImageElement.prototype) {
        const images = document.querySelectorAll('img[loading="lazy"]');
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    } else {
        // Fallback for browsers that don't support lazy loading
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js';
        document.body.appendChild(script);
    }
});
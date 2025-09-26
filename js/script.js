function selectAllFeeds() {
    const checkboxes = document.querySelectorAll('input[name="feeds[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllFeeds() {
    const checkboxes = document.querySelectorAll('input[name="feeds[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function toggleSection(sectionId) {
    const content = document.getElementById(sectionId);
    const toggleIcon = document.getElementById(sectionId + '-toggle');
    const isCollapsed = content.style.display === 'none';
    
    if (isCollapsed) {
        content.style.display = 'block';
        toggleIcon.textContent = '▼';
    } else {
        content.style.display = 'none';
        toggleIcon.textContent = '▶';
    }
    
    // Update hidden inputs in all forms
    const collapsedInputs = document.querySelectorAll('input[name="' + sectionId.replace('-management', '_collapsed') + '"]');
    collapsedInputs.forEach(input => {
        input.value = isCollapsed ? '0' : '1';
    });
    
    // Update URL with current state
    updateUrlWithState();
    
    // Save state to localStorage
    localStorage.setItem(sectionId + '-collapsed', !isCollapsed);
}

function updateUrlWithState() {
    const feedCollapsed = document.getElementById('feed-management').style.display === 'none';
    const stopwordCollapsed = document.getElementById('stopword-management').style.display === 'none';
    
    const url = new URL(window.location);
    url.searchParams.set('feed_collapsed', feedCollapsed ? '1' : '0');
    url.searchParams.set('stopword_collapsed', stopwordCollapsed ? '1' : '0');
    
    // Update URL without reloading the page
    window.history.replaceState({}, '', url);
}

// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation for removal actions
    const removeButtons = document.querySelectorAll('button[name="remove_feed"], button[name="remove_stopword"]');
    removeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this item?')) {
                e.preventDefault();
            }
        });
    });
    
    // Add word cloud animation
    const wordItems = document.querySelectorAll('.word-item');
    wordItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Restore collapsed states from URL parameters or PHP
    const sections = ['feed-management', 'stopword-management'];
    sections.forEach(sectionId => {
        const content = document.getElementById(sectionId);
        const toggleIcon = document.getElementById(sectionId + '-toggle');
        
        if (content && toggleIcon) {
            // Check URL parameters first
            const urlParams = new URLSearchParams(window.location.search);
            const feedCollapsed = urlParams.get('feed_collapsed');
            const stopwordCollapsed = urlParams.get('stopword_collapsed');
            
            let isCollapsed = false;
            
            if (sectionId === 'feed-management' && feedCollapsed !== null) {
                isCollapsed = feedCollapsed === '1';
            } else if (sectionId === 'stopword-management' && stopwordCollapsed !== null) {
                isCollapsed = stopwordCollapsed === '1';
            } else {
                // Fallback to PHP state (from inline style)
                isCollapsed = content.style.display === 'none';
            }
            
            // Apply the state
            if (isCollapsed) {
                content.style.display = 'none';
                toggleIcon.textContent = '▶';
            } else {
                content.style.display = 'block';
                toggleIcon.textContent = '▼';
            }
            
            // Update hidden inputs
            const collapsedInputs = document.querySelectorAll('input[name="' + sectionId.replace('-management', '_collapsed') + '"]');
            collapsedInputs.forEach(input => {
                input.value = isCollapsed ? '1' : '0';
            });
        }
    });
    
    // Add click handlers for section headers
    document.querySelectorAll('.section h2').forEach(header => {
        if (header.id.includes('-toggle')) {
            const sectionId = header.id.replace('-toggle', '');
            header.addEventListener('click', () => toggleSection(sectionId));
        }
    });
    
    // Update all hidden inputs when forms are submitted
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            // Update collapse state inputs before submission
            const feedCollapsed = document.getElementById('feed-management').style.display === 'none';
            const stopwordCollapsed = document.getElementById('stopword-management').style.display === 'none';
            
            const feedInputs = this.querySelectorAll('input[name="feed_collapsed"]');
            const stopwordInputs = this.querySelectorAll('input[name="stopword_collapsed"]');
            
            feedInputs.forEach(input => input.value = feedCollapsed ? '1' : '0');
            stopwordInputs.forEach(input => input.value = stopwordCollapsed ? '1' : '0');
        });
    });
    
    // Update URL with current state on page load
    updateUrlWithState();
});

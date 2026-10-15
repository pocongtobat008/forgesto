/**
 * Dark Mode Theme Toggle
 * Manages theme switching with localStorage persistence
 */
(function() {
  'use strict';
  
  // Get stored theme or detect system preference
  function getStoredTheme() {
    const stored = localStorage.getItem('sf-theme');
    if (stored) return stored;
    
    // Detect system preference
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  }
  
  // Apply theme to document
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('sf-theme', theme);
    
    // Update toggle button icons
    const sunIcons = document.querySelectorAll('.icon-sun');
    const moonIcons = document.querySelectorAll('.icon-moon');
    
    if (theme === 'dark') {
      sunIcons.forEach(el => el.style.display = 'inline');
      moonIcons.forEach(el => el.style.display = 'none');
    } else {
      sunIcons.forEach(el => el.style.display = 'none');
      moonIcons.forEach(el => el.style.display = 'inline');
    }
  }
  
  // Toggle theme
  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    applyTheme(next);
  }
  
  // Initialize on load
  function init() {
    const theme = getStoredTheme();
    applyTheme(theme);
    
    // Wire up all toggle buttons
    const toggleButtons = document.querySelectorAll('[data-theme-toggle]');
    toggleButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleTheme();
      });
    });
    
    // Listen for system preference changes
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // Only auto-switch if user hasn't explicitly set a preference
        if (!localStorage.getItem('sf-theme')) {
          applyTheme(e.matches ? 'dark' : 'light');
        }
      });
    }
  }
  
  // Run on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

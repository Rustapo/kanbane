/**
 * theme.js - Управление темой (Light/Dark/System)
 */

const Theme = {
    STORAGE_KEY: 'kanban_theme',
    
    themes: ['light', 'dark', 'system'],
    
    /**
     * Инициализация темы
     */
    init() {
        const savedTheme = this.getSavedTheme();
        this.applyTheme(savedTheme);
        this.setupListeners();
    },
    
    /**
     * Получить сохраненную тему
     */
    getSavedTheme() {
        return localStorage.getItem(this.STORAGE_KEY) || 'system';
    },
    
    /**
     * Сохранить тему
     */
    saveTheme(theme) {
        localStorage.setItem(this.STORAGE_KEY, theme);
    },
    
    /**
     * Применить тему
     */
    applyTheme(theme) {
        if (theme === 'system') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }
        this.currentTheme = theme;
    },
    
    /**
     * Переключить тему
     */
    toggle() {
        const currentIndex = this.themes.indexOf(this.getSavedTheme());
        const nextIndex = (currentIndex + 1) % this.themes.length;
        const nextTheme = this.themes[nextIndex];
        this.set(nextTheme);
        return nextTheme;
    },
    
    /**
     * Установить тему
     */
    set(theme) {
        if (!this.themes.includes(theme)) {
            throw new Error(`Invalid theme: ${theme}`);
        }
        this.saveTheme(theme);
        this.applyTheme(theme);
    },
    
    /**
     * Получить текущую эффективную тему (с учетом system)
     */
    getEffectiveTheme() {
        const saved = this.getSavedTheme();
        if (saved === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return saved;
    },
    
    /**
     * Настроить слушатели изменений
     */
    setupListeners() {
        // Слушать изменения системной темы
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        const handleChange = (e) => {
            if (this.getSavedTheme() === 'system') {
                this.applyTheme('system');
            }
        };
        
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleChange);
        } else {
            // Старый Safari
            mediaQuery.addListener(handleChange);
        }
    },
};

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Theme.init());
} else {
    Theme.init();
}

// Export
window.Theme = Theme;

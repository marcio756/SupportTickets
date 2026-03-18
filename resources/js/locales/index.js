/**
 * Resolves and maps all translation files dynamically using Vite's glob import mechanism.
 * * This module isolates the complexity of parsing multiple directory structures 
 * (e.g., pt/navbar.json, en/tickets.json) into a single nested translation object 
 * expected by Vue I18n. It automatically scales as developers add new JSON files 
 * without requiring manual imports, adhering to the Open-Closed Principle.
 */

const modules = import.meta.glob('./*/*.json', { eager: true });

const messages = {};

for (const path in modules) {
    // Extracts the locale (e.g., 'pt') and namespace (e.g., 'navbar') from the file path
    const matched = path.match(/\.\/([A-Za-z0-9-_]+)\/([A-Za-z0-9-_]+)\.json/i);
    
    if (matched && matched.length > 2) {
        const locale = matched[1];
        const namespace = matched[2];

        if (!messages[locale]) {
            messages[locale] = {};
        }

        // Assigns the JSON structure to its specific domain scope
        // Example: messages['pt']['navbar'] = { ... }
        messages[locale][namespace] = modules[path].default;
    }
}

export default messages;
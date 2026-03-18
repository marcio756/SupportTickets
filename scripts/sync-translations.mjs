/**
 * @fileoverview Automation script to synchronize and translate missing localization keys.
 * It traverses the base language directory (pt), deeply compares JSON structures with 
 * the target language directory (en), and automatically translates missing string values 
 * using the Google Translate engine. This ensures the English locale files are never 
 * missing keys introduced during development.
 */

import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';
import translate from 'translate';

// Configure the translation engine
translate.engine = 'google';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const BASE_LOCALE = 'pt';
const TARGET_LOCALE = 'en';
const LOCALES_DIR = path.resolve(__dirname, '../resources/js/locales');

/**
 * Recursively retrieves all file paths within a given directory.
 * This abstracts the file system traversal logic, ensuring we can handle nested 
 * module directories if the localization structure grows in complexity.
 * * @param {string} dir - The absolute directory path to scan.
 * @returns {Promise<string[]>} An array of absolute file paths.
 */
async function getFilesRecursively(dir) {
    let results = [];
    try {
        const list = await fs.readdir(dir, { withFileTypes: true });
        for (const dirent of list) {
            const res = path.resolve(dir, dirent.name);
            if (dirent.isDirectory()) {
                results = results.concat(await getFilesRecursively(res));
            } else if (res.endsWith('.json')) {
                results.push(res);
            }
        }
    } catch (error) {
        // Directory might not exist yet, which is fine to ignore initially
        if (error.code !== 'ENOENT') throw error;
    }
    return results;
}

/**
 * Deeply compares a base translation object with a target translation object,
 * translating and injecting any missing keys from the base into the target.
 * This recursive approach is necessary to support infinitely nested translation namespaces.
 * * @param {Object} baseObj - The source object containing the ground-truth translations (PT).
 * @param {Object} targetObj - The destination object that needs to be updated (EN).
 * @returns {Promise<boolean>} True if the target object was mutated (new keys added), false otherwise.
 */
async function syncAndTranslateObjects(baseObj, targetObj) {
    let wasModified = false;

    for (const key of Object.keys(baseObj)) {
        const baseValue = baseObj[key];

        // Handle nested objects
        if (typeof baseValue === 'object' && baseValue !== null && !Array.isArray(baseValue)) {
            if (typeof targetObj[key] !== 'object' || targetObj[key] === null) {
                targetObj[key] = {};
                wasModified = true;
            }
            const childModified = await syncAndTranslateObjects(baseValue, targetObj[key]);
            if (childModified) wasModified = true;
        } 
        // Handle string translation for missing keys
        else if (typeof baseValue === 'string') {
            if (targetObj[key] === undefined) {
                try {
                    console.log(`Translating new key: "${key}" -> "${baseValue}"`);
                    const translatedText = await translate(baseValue, { from: BASE_LOCALE, to: TARGET_LOCALE });
                    targetObj[key] = translatedText;
                    wasModified = true;
                } catch (error) {
                    console.error(`Failed to translate key "${key}":`, error.message);
                    // Fallback to base value to ensure UI doesn't break, letting devs know it needs manual fix
                    targetObj[key] = `[TODO: EN] ${baseValue}`;
                    wasModified = true;
                }
            }
        }
    }

    return wasModified;
}

/**
 * Orchestrates the synchronization process.
 * Maps base files to target files, handles JSON parsing/stringifying, and 
 * ensures non-existent target files are created.
 */
async function main() {
    console.log(`Starting localization sync: ${BASE_LOCALE} -> ${TARGET_LOCALE}...`);
    
    const baseDir = path.join(LOCALES_DIR, BASE_LOCALE);
    const targetDir = path.join(LOCALES_DIR, TARGET_LOCALE);
    
    // Ensure target directory exists
    await fs.mkdir(targetDir, { recursive: true });

    const baseFiles = await getFilesRecursively(baseDir);

    for (const baseFilePath of baseFiles) {
        // Determine corresponding target path
        const relativePath = path.relative(baseDir, baseFilePath);
        const targetFilePath = path.join(targetDir, relativePath);

        // Read and parse the base JSON file
        const baseContent = await fs.readFile(baseFilePath, 'utf-8');
        const baseJson = JSON.parse(baseContent);

        // Read and parse the target JSON file, or initialize an empty object if it doesn't exist
        let targetJson = {};
        try {
            const targetContent = await fs.readFile(targetFilePath, 'utf-8');
            targetJson = JSON.parse(targetContent);
        } catch (error) {
            if (error.code !== 'ENOENT') throw error;
            console.log(`Created new target file: ${relativePath}`);
            // Ensure nested directories exist before writing
            await fs.mkdir(path.dirname(targetFilePath), { recursive: true });
        }

        // Perform the deep sync and translation
        const hasChanges = await syncAndTranslateObjects(baseJson, targetJson);

        // Write changes to disk only if modifications occurred to prevent unnecessary I/O
        if (hasChanges) {
            await fs.writeFile(targetFilePath, JSON.stringify(targetJson, null, 4), 'utf-8');
            console.log(`Successfully updated: ${targetFilePath}`);
        }
    }

    console.log('Localization sync completed successfully.');
}

// Execute the script
main().catch(console.error);
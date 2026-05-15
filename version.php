<?php
/**
 * Plugin metadata for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_aigrader';     // Frankenstyle name.
$plugin->version   = 2026051509;           // YYYYMMDDXX. Extractors for docx + code + zip + ipynb.
$plugin->requires  = 2024100700;           // Moodle 4.5.0 minimum.
$plugin->maturity  = MATURITY_ALPHA;       // Pre-MVP.
$plugin->release   = 'v0.11.0-alpha';

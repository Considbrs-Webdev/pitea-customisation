# Pitea Customisation

A WordPress plugin for custom functionality and modifications for the Pitea WordPress installation.

## Features

- Modular PHP architecture with Composer autoloading
- Vite for modern CSS and JavaScript building
- SCSS support with variables and mixins
- Separate frontend and admin asset bundles

## Requirements

- PHP 8.0+
- Node.js 18+
- Composer

## Installation

1. Navigate to the plugin directory:
   ```bash
   cd wp-content/plugins/pitea-customisation
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies:
   ```bash
   npm install
   ```

4. Build assets:
   ```bash
   npm run build
   ```

5. Activate the plugin in WordPress admin.

## Development

### Watch for changes

```bash
npm run dev
```

Or for production watch:

```bash
npm run watch
```

### Build for production

```bash
npm run build
```

## Adding Custom Classes

1. Create a new PHP class in `source/php/Customisations/`:

   ```php
   <?php

   namespace PiteaCustomisation\Customisations;

   class MyCustomClass
   {
       public function __construct()
       {
           // Your hooks and initialization here
       }
   }
   ```

2. Register the class in `source/php/App.php`:

   ```php
   private function registerInstances(): void
   {
       $classes = [
           Customisations\IconReplacer::class,
           Customisations\MyCustomClass::class, // Add your class here
       ];
       // ...
   }
   ```

3. Run `composer dump-autoload` to update the autoloader.

## Directory Structure

```
pitea-customisation/
├── dist/                    # Built assets (generated)
├── source/
│   ├── js/
│   │   ├── main.js          # Frontend JavaScript entry
│   │   └── admin.js         # Admin JavaScript entry
│   ├── php/
│   │   ├── App.php          # Main plugin class
│   │   └── Customisations/  # Custom functionality classes
│   │       └── IconReplacer.php
│   └── sass/
│       ├── style.scss       # Frontend styles entry
│       ├── admin.scss       # Admin styles entry
│       └── abstracts/       # Variables, mixins, etc.
├── composer.json
├── package.json
├── vite.config.js
└── pitea-customisation.php  # Main plugin file
```

## License

MIT

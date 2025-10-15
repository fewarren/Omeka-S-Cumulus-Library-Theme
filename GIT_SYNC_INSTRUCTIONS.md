# Git Sync Instructions - Remove LibraryThemeStyles Module Files

## Current Situation

The local `/home/fwarren/library-theme` repository is on branch "Test" with no commits yet, while the GitHub repository has a "main" branch with existing commits. The local working directory has all files staged but not committed.

## Files Already Removed Locally

The following LibraryThemeStyles module files have been removed from the local filesystem:
- `src/Config/ModuleConfig.php`
- `src/Service/ErrorHandler.php`
- `src/Service/ThemeSettingsService.php`
- `src/Service/ErrorHandlerFactory.php`
- `src/Service/ModuleConfigService.php`
- `src/Service/PresetManagerFactory.php`
- `src/Service/AdminControllerFactory.php`
- `src/Service/ThemeSettingsServiceFactory.php`
- `src/Service/ModuleConfigServiceFactory.php`
- `src/Service/PresetManager.php`
- `src/Controller/AdminController.php`

Additionally, `config/module.config.php` has been modified to remove LibraryThemeStyles service configuration.

## Git Commands to Sync with GitHub

Execute these commands in order:

```bash
cd /home/fwarren/library-theme

# Step 1: Reset the current branch state
git reset

# Step 2: Delete the Test branch and checkout main from remote
git checkout -B main origin/main

# Step 3: Remove the LibraryThemeStyles module files from the repository
git rm src/Config/ModuleConfig.php
git rm src/Service/ErrorHandler.php
git rm src/Service/ThemeSettingsService.php
git rm src/Service/ErrorHandlerFactory.php
git rm src/Service/ModuleConfigService.php
git rm src/Service/PresetManagerFactory.php
git rm src/Service/AdminControllerFactory.php
git rm src/Service/ThemeSettingsServiceFactory.php
git rm src/Service/ModuleConfigServiceFactory.php
git rm src/Service/PresetManager.php
git rm src/Controller/AdminController.php

# Step 4: Stage the modified module.config.php
git add config/module.config.php

# Step 5: Check what will be committed
git status

# Step 6: Commit the changes
git commit -m "Remove LibraryThemeStyles module code from theme

Moved LibraryThemeStyles module to separate repository/directory.
The module code should not be part of the theme repository.

Files removed:
- src/Config/ModuleConfig.php
- src/Service/ErrorHandler.php
- src/Service/ThemeSettingsService.php
- src/Service/ErrorHandlerFactory.php
- src/Service/ModuleConfigService.php
- src/Service/PresetManagerFactory.php
- src/Service/AdminControllerFactory.php
- src/Service/ThemeSettingsServiceFactory.php
- src/Service/ModuleConfigServiceFactory.php
- src/Service/PresetManager.php
- src/Controller/AdminController.php

Files modified:
- config/module.config.php (removed module service configuration)

The LibraryThemeStyles module now resides in its own directory
at /home/fwarren/LibraryThemeStyles and is deployed separately
to /var/www/omeka-s/modules/LibraryThemeStyles."

# Step 7: Push to GitHub
# You will be prompted for username and password
# Username: fewarren
# Password: MeherBaba1
git push origin main
```

## Verification

After pushing, verify the changes:

```bash
# Check that the files are removed from the repository
git ls-files | grep -E "(Config|Service|Controller)" | grep -v View

# Should only show:
# config/module.config.php
# config/theme.ini

# Verify the remote is in sync
git status
# Should show: "Your branch is up to date with 'origin/main'"
```

## Expected Result

- GitHub repository will have the LibraryThemeStyles module files removed
- `config/module.config.php` will contain only theme configuration
- Local and remote repositories will be in sync
- The theme repository will be clean and contain only theme-specific files

## Notes

- The LibraryThemeStyles module files still exist in `/home/fwarren/LibraryThemeStyles` (separate directory)
- The module is deployed separately to `/var/www/omeka-s/modules/LibraryThemeStyles`
- This separation maintains proper architecture: theme in one repo, module in another


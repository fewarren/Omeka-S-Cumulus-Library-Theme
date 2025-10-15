# Git Sync Instructions - Approach 3 (Simplest)

## Overview

This approach treats your local directory as the source of truth. Since you've already removed the LibraryThemeStyles module files locally and modified `config/module.config.php`, we'll commit these changes and then merge with the remote repository.

## Current State

- **Local directory**: LibraryThemeStyles module files already removed, `config/module.config.php` already cleaned
- **Remote repository**: Still contains LibraryThemeStyles module files
- **Goal**: Push local changes to remove module files from remote

## Step-by-Step Commands

Execute these commands in order:

```bash
cd /home/fwarren/library-theme

# Step 1: Create/switch to main branch (without checking out remote yet)
git checkout -B main

# Step 2: Set upstream tracking to origin/main
git branch --set-upstream-to=origin/main main

# Step 3: Add all your local files (module files already removed)
git add -A

# Step 4: Check what will be committed
git status

# You should see many new files and the removed module files
# Verify that src/Config/, src/Service/, src/Controller/ files are marked as deleted

# Step 5: Commit your local changes
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

# Step 6: Pull and merge with remote
# This will merge the remote history with your local commit
git pull origin main --allow-unrelated-histories

# Step 7: Handle merge conflicts (if any)
# If git reports conflicts, you'll need to resolve them:
# - Edit the conflicted files
# - Look for conflict markers: <<<<<<<, =======, >>>>>>>
# - Choose which version to keep (usually your local version)
# - Remove the conflict markers
# - Then run:
#   git add <resolved-file>
#   git commit -m "Resolve merge conflicts"

# Step 8: Push to GitHub
# You will be prompted for credentials:
# Username: fewarren
# Password: MeherBaba1
git push origin main

# If push is rejected, use force push (since we're rewriting history):
# git push origin main --force
```

## Expected Merge Conflicts

You may see conflicts in files that exist both locally and remotely but have different content. Common conflicts might be:

1. **config/module.config.php** - Your version has module config removed, remote has it
   - **Resolution**: Keep your local version (without module config)

2. **README.md** or other documentation files
   - **Resolution**: Review and merge manually, or keep your local version

3. **Theme files** that you've modified locally
   - **Resolution**: Keep your local version

## Resolving Conflicts Example

If you get a conflict in `config/module.config.php`:

```bash
# Open the file
nano config/module.config.php

# You'll see something like:
# <<<<<<< HEAD
# <?php
# return [
#     'themes' => [
# =======
# <?php
# return [
#     'service_manager' => [
#         'factories' => [
#             \LibraryThemeStyles\Service\ErrorHandler::class => ...
# >>>>>>> origin/main

# Keep the HEAD version (your local changes), remove conflict markers
# Save and exit

# Stage the resolved file
git add config/module.config.php

# Complete the merge
git commit -m "Resolve merge conflicts - keep local version without module config"

# Then push
git push origin main
```

## Verification After Push

```bash
# Check that you're in sync with remote
git status
# Should show: "Your branch is up to date with 'origin/main'"

# Verify module files are removed from repository
git ls-files | grep -E "src/(Config|Service|Controller)"
# Should only show: src/View/Helper/ThemeFunctions.php

# Check remote repository on GitHub
# Navigate to: https://github.com/fewarren/Omeka-S-Library-Theme
# Verify that src/Config/, src/Service/, src/Controller/ directories are gone
```

## Troubleshooting

### If push is rejected with "non-fast-forward" error:

```bash
# Force push (overwrites remote history)
git push origin main --force
```

### If you want to see what will be merged before pulling:

```bash
# Fetch remote changes without merging
git fetch origin main

# Compare your local with remote
git diff main origin/main

# If satisfied, proceed with pull
git pull origin main --allow-unrelated-histories
```

### If something goes wrong and you want to start over:

```bash
# Create a backup of your local changes
cp -r /home/fwarren/library-theme /home/fwarren/library-theme-backup

# Reset to remote state
git fetch origin
git reset --hard origin/main

# Then manually remove module files again and commit
```

## Success Criteria

✅ Local repository on `main` branch  
✅ All local changes committed  
✅ Merged with remote repository history  
✅ LibraryThemeStyles module files removed from repository  
✅ `config/module.config.php` contains only theme configuration  
✅ Changes pushed to GitHub successfully  
✅ Local and remote in sync  

## Notes

- The `--allow-unrelated-histories` flag is needed because your local branch has no common ancestor with the remote branch
- This is safe because we're merging two versions of the same project
- After this initial sync, future git operations will work normally
- The LibraryThemeStyles module remains in `/home/fwarren/LibraryThemeStyles` (separate directory)

## GitHub Credentials

When prompted during `git push`:
- **Username**: `fewarren`
- **Password**: `MeherBaba1`

---

**Execute these commands and report any errors or conflicts that occur.**


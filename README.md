# PHP Remote Terminal

A secure, web-based and CLI-based terminal emulator for remote management of your PHP projects and server environments. Includes full integration for Windows Terminal!

## Features
- **Frontend (Web):** Full Xterm.js implementation with ANSI color support and CaskaydiaCove Nerd Font.
- **Backend (API):** Robust authentication with hashed passwords and anti-path-traversal protection.
- **Client (CLI):** Native PHP CLI application to connect from your local terminal.
- **Commands:** Support for `ls`, `cd`, `pwd`, `mkdir`, `rm`, `cat`, `touch`, `clear`, `adduser`, `addpath`, and more.

## Installation & First Startup
1. Upload all files to your web server.
2. Open the URL in your browser or run the `RemoteTerminal.bat` locally.
3. **Setup Mode:** On the first run, the system will detect that no users exist and will prompt you to create an **Admin Account**. 
4. Once created, a `users.json` file is generated, and setup mode is disabled.

## Security
- **System Access:** Navigate and manage your filesystem (based on PHP process permissions).
- **Authentication:** Uses HTTP Basic Auth with passwords hashed via `password_hash()`.
- **Session Safety:** Credentials are never hardcoded in the frontend source; they are provided via secure prompts and stored in `sessionStorage`.

## Windows Terminal Integration
1. Open Windows Terminal -> Settings -> Add a new profile.
2. For **Command Line**, enter the full path to `RemoteTerminal.bat`.
3. Save and open the profile. 
4. On the first connection, it will guide you through the setup or login process.

## Command Development
To add new functionality, simply create a `[command].php` file in the `bin/` directory.

Each command receives a `$context` array containing:
- `$context['args']`: Array of arguments.
- `$context['cwd']`: The virtual current working directory.
- `$context['user']`: The currently logged-in username.
- `$context['resolve_path']($cwd, $target)`: A **mandatory** helper to convert virtual paths to safe server paths. Returns `false` if the path is malicious.

Example return: `return ['output' => 'Success!', 'exit_code' => 0];`

## Management
- **Add User:** Use the `adduser <username> <password>` command.
- **Add Path:** Use the `addpath <relative_dir_path>` command to include more command directories.

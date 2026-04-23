@echo off
TITLE PHP Remote Terminal
setlocal EnableDelayedExpansion

REM Check if curl is available
curl --version >nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo [ERROR] curl is not installed or not in the Windows PATH.
    echo curl is included by default on Windows 10 1803+ and Windows 11.
    pause
    exit /b 1
)

REM Check if PowerShell is available (needed for JSON parsing)
powershell -Command "exit 0" >nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PowerShell is not available on this system.
    pause
    exit /b 1
)

REM Credentials file (stored next to this script)
set "CRED_FILE=%~dp0client_credentials.txt"
set "HOST_URL="
set "USERNAME="
set "PASSWORD="

REM Load credentials from file if it exists
if exist "%CRED_FILE%" (
    for /f "usebackq tokens=1,* delims==" %%A in ("%CRED_FILE%") do (
        if "%%A"=="host" set "HOST_URL=%%B"
        if "%%A"=="username" set "USERNAME=%%B"
        if "%%A"=="password" set "PASSWORD=%%B"
    )
    if not "!USERNAME!"=="" (
        for /f "delims=" %%R in ('powershell -NoProfile -Command "[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('!USERNAME!:!PASSWORD!'))"') do set "B64CREDS=%%R"
    )
)

REM Ask for host if not set
if "!HOST_URL!"=="" (
    echo Welcome to the PHP Remote Terminal Client!
    set /p "HOST_URL=Host URL (e.g. http://localhost:8000/api.php): "
)

REM ---------------------------------------------------------------
REM Check server status (setup mode / auth required)
REM ---------------------------------------------------------------
:CHECK_STATUS
set "TEMP_RESPONSE=%TEMP%\phpterminal_resp.json"

curl -k -s -X POST "!HOST_URL!" ^
    -H "Content-Type: application/json" ^
    -H "Authorization: Basic !B64CREDS!" ^
    -d "{}" ^
    -o "!TEMP_RESPONSE!" 2>nul

REM Parse require_setup flag
for /f "delims=" %%R in ('powershell -NoProfile -Command "try { $j = Get-Content '!TEMP_RESPONSE!' -Raw | ConvertFrom-Json; if ($j.require_setup -eq $true) { 'YES' } else { 'NO' } } catch { 'NO' }"') do set "NEEDS_SETUP=%%R"

REM Parse auth error
for /f "delims=" %%R in ('powershell -NoProfile -Command "try { $j = Get-Content '!TEMP_RESPONSE!' -Raw | ConvertFrom-Json; if ($j.error -like '*Authentication*') { 'YES' } else { 'NO' } } catch { 'NO' }"') do set "AUTH_FAILED=%%R"

REM ---------------------------------------------------------------
REM First startup setup
REM ---------------------------------------------------------------
if "!NEEDS_SETUP!"=="YES" (
    echo.
    echo [!] SERVER REQUIRES INITIAL SETUP
    set /p "SETUP_USER=Choose an Admin Username: "
    set /p "SETUP_PASS=Choose an Admin Password: "

    curl -k -s -X POST "!HOST_URL!" ^
        -H "Content-Type: application/json" ^
        -d "{\"setup_user\":\"!SETUP_USER!\",\"setup_pass\":\"!SETUP_PASS!\"}" ^
        -o "!TEMP_RESPONSE!" 2>nul

    for /f "delims=" %%R in ('powershell -NoProfile -Command "try { $j = Get-Content '!TEMP_RESPONSE!' -Raw | ConvertFrom-Json; $j.exit_code } catch { '1' }"') do set "SETUP_CODE=%%R"

    if "!SETUP_CODE!"=="0" (
        echo Setup complete! Logging in...
        set "USERNAME=!SETUP_USER!"
        set "PASSWORD=!SETUP_PASS!"
        goto :SAVE_CREDS
    ) else (
        echo [ERROR] Setup failed.
        pause
        exit /b 1
    )
)

REM ---------------------------------------------------------------
REM Login prompt if no credentials or auth failed
REM ---------------------------------------------------------------
if "!USERNAME!"=="" goto :LOGIN
if "!AUTH_FAILED!"=="YES" (
    echo [ERROR] Authentication failed. Please log in again.
    goto :LOGIN
)
goto :COMPUTE_B64

:LOGIN
set /p "USERNAME=Username: "
set /p "PASSWORD=Password: "

:SAVE_CREDS
(
    echo host=!HOST_URL!
    echo username=!USERNAME!
    echo password=!PASSWORD!
) > "!CRED_FILE!"

:COMPUTE_B64
REM Encode credentials to Base64 for Basic Auth
for /f "delims=" %%R in ('powershell -NoProfile -Command "[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('!USERNAME!:!PASSWORD!'))"') do set "B64CREDS=%%R"

REM Verify credentials by checking status again
curl -k -s -X POST "!HOST_URL!" ^
    -H "Content-Type: application/json" ^
    -H "Authorization: Basic !B64CREDS!" ^
    -d "{\"command_line\":\"whoami\",\"cwd\":\"/\"}" ^
    -o "!TEMP_RESPONSE!" 2>nul

for /f "delims=" %%R in ('powershell -NoProfile -Command "try { $j = Get-Content '!TEMP_RESPONSE!' -Raw | ConvertFrom-Json; if ($j.error -like '*Authentication*') { 'YES' } else { 'NO' } } catch { 'NO' }"') do set "AUTH_FAILED=%%R"

if "!AUTH_FAILED!"=="YES" (
    echo [ERROR] Authentication failed. Please check your credentials.
    set "USERNAME="
    set "PASSWORD="
    goto :LOGIN
)

REM ---------------------------------------------------------------
REM Main terminal loop
REM ---------------------------------------------------------------
set "CWD=/"

cls
echo Connected to: !HOST_URL!
echo Type 'exit' or 'quit' to disconnect.
echo.

:LOOP
set /p "CMD=!USERNAME!@phpterminal !CWD!> "

if /i "!CMD!"=="exit" goto :END
if /i "!CMD!"=="quit" goto :END
if "!CMD!"=="" goto :LOOP

REM Escape quotes in CMD for JSON
set "CMD_ESCAPED=!CMD:"=\"!"

REM Send command to API
curl -k -s -X POST "!HOST_URL!" ^
    -H "Content-Type: application/json" ^
    -H "Authorization: Basic !B64CREDS!" ^
    -d "{\"command_line\":\"!CMD_ESCAPED!\",\"cwd\":\"!CWD!\"}" ^
    -o "!TEMP_RESPONSE!" 2>nul

REM Check for network error
if not exist "!TEMP_RESPONSE!" (
    echo [Network Error] Could not reach the server.
    goto :LOOP
)

REM Check for empty response
for %%A in ("!TEMP_RESPONSE!") do set "RESP_SIZE=%%~zA"
if "!RESP_SIZE!"=="0" (
    echo [Empty Response] Server returned nothing.
    goto :LOOP
)

REM Extract and print output
for /f "delims=" %%R in ('powershell -NoProfile -Command "try { $j = Get-Content '!TEMP_RESPONSE!' -Raw | ConvertFrom-Json; if ($j.error) { Write-Host $j.error } elseif ($j.output) { $j.output } } catch { $content = Get-Content '!TEMP_RESPONSE!' -Raw; if ($content -match '<html|<title>|Error') { Write-Host '[Server Error: HTML response]' } elseif ($content.Length -lt 2) { Write-Host '[Empty Response]' } else { Write-Host '[Parse Error] Raw:'; Write-Host $content.Substring(0, [Math]::Min(100, $content.Length)) } }"') do echo %%R

REM Update cwd if changed
for /f "delims=" %%R in ('powershell -NoProfile -Command "try { $j = Get-Content '!TEMP_RESPONSE!' -Raw | ConvertFrom-Json; if ($j.new_cwd) { $j.new_cwd } else { '!CWD!' } } catch { '!CWD!' }"') do set "CWD=%%R"

goto :LOOP

:END
echo Disconnected.
endlocal

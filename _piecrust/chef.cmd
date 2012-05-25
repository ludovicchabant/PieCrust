@echo off
setlocal

set CHEF_DIR=%~dp0
for %%F in ("%CHEF_DIR%..\") do set ROOT_DIR=%%~fF
echo '%CHEF_DIR%chef.cmd' is deprecated. Please use '%ROOT_DIR%bin\chef.cmd' instead.
%ROOT_DIR%bin\chef.cmd %*

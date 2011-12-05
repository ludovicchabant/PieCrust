@echo off
setlocal

set CWD=%~dp0

set THIS_REPO=%CWD%..
set APP_REPO=%THIS_REPO%..\PieCrust_App
set SAMPLE_REPO=%THIS_REPO%..\PieCrust_Sample

rem Exporting app-only repository.
hg convert --filemap %CWD%\piecrust_app_filemap %THIS_REPO% %APP_REPO%

rem Exporting sample website repository.
hg convert --filemap %CWD%\piecrust_sample_filemap %THIS_REPO% %SAMPLE_REPO%


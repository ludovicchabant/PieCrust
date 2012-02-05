@echo off
setlocal

set CUR_DIR=%~dp0
set CHEF=%CUR_DIR%..\_piecrust\chef
set OUT_DIR=%CUR_DIR%..\_piecrust\resources\messages
set ROOT_DIR=%CUR_DIR%messages

%CHEF% bake -o %OUT_DIR% %ROOT_DIR%
del %OUT_DIR%\index.html


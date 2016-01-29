#! /bin/sh

### BEGIN INIT INFO
# Provides:          app_server
# Required-Start:    $remote_fs $network
# Required-Stop:     $remote_fs $network
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: starts app_server
# Description:       starts the PHP FastCGI Process Manager daemon
### END INIT INFO

PHP_BIN=/usr/bin/php

#代码根目录
SERVER_PATH=/opt/webServer/app


getMasterPid()
{
    PID=`/bin/ps axu|grep app_server|grep -v "grep"|awk '{print $2}'`
    echo $PID
}

getManagerPid()
{
    MID=`/bin/ps axu|grep 'HttpServer.php'|grep -v "grep"|awk '{print $2}'`
    echo $MID
}
case "$1" in
        start)
                PID=`getMasterPid`
                if [ -n "$PID" ]; then
                    echo -n "app server is running"
                    exit 1
                fi
                echo -n "Starting app server "

                $PHP_BIN $SERVER_PATH/server/swoole/HttpServer.php &
                echo " done"
        ;;

        stop)
                PID=`getMasterPid`
                if [ -z "$PID" ]; then
                    echo -n "app server is not running"
                    exit 1
                fi
                echo -n "Gracefully shutting down app server "

                kill $PID
                echo " done"
        ;;

        status)
                PID=`getMasterPid`
                if [ -n "$PID" ]; then
                    echo -n "app server is running"
                else
                    echo -n "app server is not running"
                fi
        ;;

        force-quit)
                $0 stop
        ;;

        restart)
                $0 stop
                $0 start
        ;;

        reload)
                MID=`getManagerPid`
                if [ -z "$MID" ]; then
                    echo -n "app server is not running"
                    exit 1
                fi

                echo -n "Reload service app_server "

                kill -USR1 $MID

                echo " done"
        ;;

        reloadtask)
                MID=`getManagerPid`
                if [ -z "$MID" ]; then
                    echo -n "app server is not running"
                    exit 1
                fi

                echo -n "Reload service app_server"

                kill -USR2 $MID

                echo " done"
        ;;

        *)
                echo "Usage: $0 {start|stop|force-quit|restart|reload|reloadtask|status}"
                exit 1
        ;;

esac
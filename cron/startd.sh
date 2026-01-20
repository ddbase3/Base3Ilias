#!/bin/bash
NAME=$1
case "$2" in
        start)
                #if [ -e "$NAME.pid" ]; then
                        #$0 $1 stop
                #fi

                        echo "starte $NAME"
                        if [ -e "$NAME" ]; then
                                if [ ! -x $NAME ]; then
                                        echo "$NAME is not executable, changing this"
                                        chmod u+x $NAME
                                fi
                                if [ -x $NAME ]; then
                                        sed -i 's/\x0D$//' $NAME
                                        $NAME 1>/dev/null 2>&1 &
                                        echo $! > $NAME.pid
                                else
                                        echo "$NAME is not executable, please change!"
                                        exit 4
                                fi
                        else
                                echo "$NAME not found"
                                exit 5
                        fi

        ;;
    stop)
                if [ -e "$NAME.pid" ]; then
                        echo "stoppe den $NAME"
                        CPIDS=$(pgrep -P `cat $NAME.pid`)

                        (kill -KILL $CPIDS > /dev/null 2>&1 &)

                        if [ -e "$NAME.pid" ]; then
                                kill -KILL `cat $NAME.pid` > /dev/null 2>&1
                                rm $NAME.pid
                                sleep 1
                        else
                                echo "done"
                        fi
                else
                        echo "$NAME.pid is missing, no started daemon?"
                        exit 7
                fi
        ;;
        restart)
                $0 $1 stop && $0 $1 start || exit 1
        ;;
        status)
                if [ -e "$NAME.pid" ]; then
                        echo "the daemon seems to be running"
                        exit 0
                else
                        echo "the daemon seems to be stopped"
                        exit 3
                fi
        ;;
        *)
                echo "Usage: $0 <service> {start|stop|restart|status}"
                exit 2
esac
exit 0


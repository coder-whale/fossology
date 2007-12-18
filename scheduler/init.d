#! /bin/sh
# Init script for the scheduler.
#
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# Based on the example skeleton:
#	Written by Miquel van Smoorenburg <miquels@cistron.nl>.
#	Modified for Debian 
#	by Ian Murdock <imurdock@gnu.ai.mit.edu>.
#	@(#)skeleton  1.9  26-Feb-2001  miquels@cistron.nl
#

PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
DAEMON=AGENTDIR/scheduler
NAME=scheduler
DESC="FOSS analysis job scheduler"

test -x $DAEMON || exit 0

# default is to run, can be overridden in defaults
ENABLED=1

# Include scheduler defaults if available
SCHEDULEROPT="-d"
if [ -f /etc/default/fossology ] ; then
	# This can override SCHEDULEROPT.
	# Be sure to keep "-d" for daemon mode
	. /etc/default/fossology
fi

# Quit quietly, if $ENABLED is 0.
test "$ENABLED" != "0" || exit 0

set -e

case "$1" in
  start)
	echo -n "Starting $DESC: "
	$DAEMON $SCHEDULEROPT
	echo "$NAME."
	;;
  stop)
	echo -n "Stopping $DESC: "
	$DAEMON -k
	echo "$NAME."
	;;
  #reload)
	#
	#	If the daemon can reload its config files on the fly
	#	for example by sending it SIGHUP, do it here.
	#
	#	If the daemon responds to changes in its config file
	#	directly anyway, make this a do-nothing entry.
	#
	# echo "Reloading $DESC configuration files."
	# start-stop-daemon --stop --signal 1 --quiet --pidfile \
	#	/var/run/$NAME.pid --exec $DAEMON
  #;;
  force-reload)
	#
	#	If the "reload" option is implemented, move the "force-reload"
	#	option to the "reload" entry above. If not, "force-reload" is
	#	just the same as "restart" except that it does nothing if the
	#   daemon isn't already running.
	# check wether $DAEMON is running. If so, restart
	$DAEMON -k
	$DAEMON $SCHEDULEROPT
	;;
  restart)
    echo -n "Restarting $DESC: "
	$DAEMON -k
	$DAEMON $SCHEDULEROPT
	;;
  *)
	N=/etc/init.d/$NAME
	# echo "Usage: $N {start|stop|restart|reload|force-reload}" >&2
	echo "Usage: $N {start|stop|restart|force-reload}" >&2
	exit 1
	;;
esac

exit 0

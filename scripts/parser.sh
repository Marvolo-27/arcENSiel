#!/bin/bash
##
## Custom arguments parser
##

# Available options
parameters=($parameters "--help")
named_parameters=($named_parameters)

# Test function
test_param() {
	# returns true if option is valid, false otherwise
	
	for ok_param in "${parameters[@]}" ; do	
		# Breaks loop at first correspondance: option is valid
		if [[ $1 == $ok_param ]] ; then
			echo true
            return
		fi
	done
	
    for ok_param in "${named_parameters[@]}" ; do	
		# Breaks loop at first correspondance: option is valid
        if [[ $ok_param == $(cut -s -d= -f1 <<< $1) && -n $(cut -s -d= -f2 <<< $1) ]] ; then
            echo true
            return
		fi
	done

    echo false
	}

# Récupération arguments
args="$@"

# change $args to a placeholder to prevent errors with commands
if [[ -z $args ]] ; then 
	args='[empty]'
	fi

# searches for invalid parameters - use array-converted $args
if [[ $args != '[empty]' ]] ; then
	args_test=($args)
    for param in "${args_test[@]}" ; do
		if ! $(test_param "$param") ; then
			echo "Unkown parameter or invalid argument: $param"
			echo -e "\nRun '${BASH_SOURCE[1]} --help' to show available options" && exit 1
		fi
	done	
fi

# Removes conflictual characters
args=$(echo $args | sed "s/--//g" | sed "s/\s/\n/g")
if [[ -z $args ]] ; then args='[empty]' ; fi

# Help message
if [[ -n $(echo $args | grep 'help') ]] ; then
	echo "${help:-This script provides no help documentation}" && exit 0
fi
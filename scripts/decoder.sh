#!/bin/bash

decode () {
    root=${WEBROOT:-$HOME/www/arcensiel}
    dir=$root/.private
    name=${1:?usage: decode TOKEN_ID}   # fails if undefined
    file=$dir/$name

    if [[ ! -f "$file.meta" || ! -f "$file.dat" || ! -f "$file.key" ]]; then
        echo "Missing at least one decryption file for identifier '$name'" >&2
        return
    fi

    # od reads 8 bytes total (N8) as chunks of 4 bytes interprated as unsigned decimals (tu4), ignores offset (An) and explicitly writes duplicates (-v)
    # use process substitution into read to split output as 2 variables
        # -- marks end of arguments to prevent misread of inputs
    read -r offset length < <(od -An -tu4 -N8 -v -- "$file.meta")

    # read bytes from offset, then cut at lengtg, and read content as chunks of 1 byte hex
    # parse output as array
    masked=( $(tail -c +"$(($offset + 1))" "$file.dat" | head -c "$length" | od -An -tx1 -v) )
    mask=( $(od -An -tx1 -v "$file.key") )
    
    # unmask password character per character
    # printf format b as an hex string char - \ escaping and 2-digit hex formatting (%02x)
    decoded=
    for (( i=0; i < ${#mask[@]}; i++ )); do
        printf -v b '\\x%02x' $(( 0x${mask[i]} ^ 0x${masked[i]} ))
        decoded+=$b
    done

    # print result and expand escaped character
    printf "%b\n" "$decoded"
}
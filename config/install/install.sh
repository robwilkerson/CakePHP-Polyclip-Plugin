#!/bin/bash

if [ $# -eq 0 ]; then
     echo "Usage: ./mkassets.sh <instance root>"
     exit 1
fi

basedir=$1
letters="a b c d e f g h i j k l m n o p q r s t u v q r s t u v w x y z"

if [ ! -d $basedir ]; then
  mkdir $basedir
fi

cd $basedir
mkdir -p assets/_tmp
cd assets 

for i in $letters
do
     for j in $letters
     do
          mkdir -p $i/$j
     done
done
chmod -R 777 .

exit 0

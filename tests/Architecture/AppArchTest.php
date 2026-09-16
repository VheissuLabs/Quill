<?php

arch()
    ->preset()
    ->laravel();

arch()
    ->preset()
    ->security();

arch()
    ->expect('App')
    ->toBeCasedCorrectly();

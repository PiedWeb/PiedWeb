<?php

namespace PiedWeb\Rison;

abstract class Rison
{
    protected string $notIdchar = " '!:(),*@$";

    protected string $notIdstart = '-0123456789';
}

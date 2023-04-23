<?php

namespace PiedWeb\Google\Result;

final class BusinessResult
{
    public string $cid;

    public string $name;

    public int $organicPos;

    public int $position;

    public int $pixelPos = 0;

    public bool $ads = false;
}

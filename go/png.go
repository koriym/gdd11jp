package main

import (
    "fmt"
    "io"
    "strings"
	imagep "image/png"
    /* add more */
)

func CountColor(png io.Reader) int {
	image, err := imagep.Decode(png)
	if err != nil {
	    fmt.Printf("Error from png.Decode: %s\n", err)
	}
	width := image.Bounds().Size().X 
	height := image.Bounds().Size().Y
    //fmt.Printf("width: %d, %d\n", width, height)
	result := map[uint32]bool{}
	var idx uint32
	for y:=0 ; y < height ; y++{
		for x:= 0 ; x < width ; x++ {
			color := image.At(x, y)
		 	//fmt.Printf("x, y, color: %d %d %x\n", x, y, color)
			r, g, b, _ := color.RGBA();
			idx = r*0x100*0x100 + g*0x100 + b
			result[idx] = true
		}
	}
	cnt := len(result)
    return cnt
}
# CSS
The code includes [Bootstrap](https://getbootstrap.com/). We only need
`bootstrap.min.css`. It is copy to `web/css/bootstrap.min.css`.

All Bootstrap classes that we use:

    $ grep -r 'class="[^"]*"' views | sed 's/.*class="\([^"]*\)".*/\1/g' | uniq | sort | uniq

This can be used to generate a `config.json` file on the 
[Customize](https://getbootstrap.com/customize/) page and only include the 
relevant CSS components.

We currently do not use any fonts, themes or JavaScript.

Instances can override this `bootstrap.min.css` with their own branded version
if required.

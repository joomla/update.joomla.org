# Joomla! Update Channel

This repository provides the source for the Joomla Core Update Server
which powers the [update.joomla.org](http://update.joomla.org) website.

## Testing

If you would like to test the Joomla Core Update Server,
you can serve the `www` folder (which contains the XML files) using a web server.
For example, you can use a one-liner with Docker to serve it on `localhost`, port 8080:
```
docker run --rm -p 8080:80 -v "$PWD/www:/usr/share/nginx/html:ro" nginx:alpine
```

Configure Joomla Update options in the Joomla Administrator backend.
If you're testing a new release candidate, use:
* Update Channel: Custom URL
* Minimum Stability: Release Candidate
* Custom URL: http://localhost:8080/core/test/list_test.xml

:point_right: Be aware this is only the first step.
              You have to replace `https://update.joomla.org` with `http://localhost:8080` in all used XML files.

# More Information

For more details about the Joomla Core Update Server, refer to the Joomla documentation:
* [Release and support cycle](https://docs.joomla.org/Release_and_support_cycle)
* [Joomla: Manage_the_Joomla_Core_Update_Server](https://docs.joomla.org/Joomla:Manage_the_Joomla_Core_Update_Server)

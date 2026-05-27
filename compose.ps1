param($env = "dev")

# Lance le docker compose en fonction de l'environnement spécifié ou en dev si non spécifié
function Start-Compose
{
    param($env)

    if($env -ne "dev" -and $env -ne "prod")
    {
        throw "Invalid environment specified: `"$env`".`nPlease use `"dev`" or `"prod`"."
    }
    
    if($env -eq "dev")
    {
        echo "Starting compose for dev environment"
        docker compose -f ./compose.base.yaml -f ./compose.dev.yaml up -d --build
    }

    if($env -eq "prod")
    {
        echo "Starting compose for prod environment"
        docker compose -f ./compose.base.yaml -f ./compose.prod.yaml pull
        docker compose -f ./compose.base.yaml -f ./compose.prod.yaml up -d
    }
}

Start-Compose -env $env
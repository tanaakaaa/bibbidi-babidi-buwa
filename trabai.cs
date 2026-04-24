using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.DependencyInjection;
using System.Collections.Generic;
using System.Linq;

var builder = WebApplication.CreateBuilder(args);
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

var app = builder.Build();

app.UseSwagger();
app.UseSwaggerUI(config => {
    config.SwaggerEndpoint("/swagger/v1/swagger.json", "Minha API v1");
    config.RoutePrefix = string.Empty;
});

var produtos = new List<Produto>();

app.MapPost("/produtos", (Produto produto) => {
    produtos.Add(produto);
    return Results.Created($"/produtos/{produto.Id}", produto);
});

app.MapGet("/produtos", () => Results.Ok(produtos));

app.MapGet("/produtos/{id}", (int id) => {
    var produto = produtos.FirstOrDefault(p => p.Id == id);
    return produto is not null ? Results.Ok(produto) : Results.NotFound("Produto não encontrado.");
});

app.MapPut("/produtos/{id}", (int id, Produto produtoAtualizado) => {
    var produto = produtos.FirstOrDefault(p => p.Id == id);
    if (produto is null) return Results.NotFound("Produto não encontrado.");

    produto.Nome = produtoAtualizado.Nome;
    produto.Preco = produtoAtualizado.Preco;
    return Results.Ok(produto);
});

app.MapDelete("/produtos/{id}", (int id) => {
    var produto = produtos.FirstOrDefault(p => p.Id == id);
    if (produto is null) return Results.NotFound("Produto não encontrado.");

    produtos.Remove(produto);
    return Results.Ok("Produto excluído com sucesso.");
});

app.Run();

public class Produto {
    public int Id { get; set; }
    public string Nome { get; set; } = string.Empty;
    public decimal Preco { get; set; }
}
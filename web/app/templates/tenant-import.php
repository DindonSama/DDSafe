<!-- Import CSV template -->
<div class="container mt-5">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Importer des membres</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/tenants/members/bulk-import" enctype="multipart/form-data">
                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($tid); ?>">
                        
                        <div class="form-group mb-3">
                            <label for="csv_file" class="form-label">Fichier CSV</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <small class="text-muted">Format : email,role (role optionnel, par défaut "member")</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Importer</button>
                        <a href="/tenants/manage?id=<?php echo htmlspecialchars($tid); ?>" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>Format du fichier CSV</h5>
                </div>
                <div class="card-body">
                    <p>Votre fichier CSV doit avoir les colonnes suivantes :</p>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>email</th>
                                <th>role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>john@example.com</td>
                                <td>member</td>
                            </tr>
                            <tr>
                                <td>jane@example.com</td>
                                <td>editor</td>
                            </tr>
                            <tr>
                                <td>admin@example.com</td>
                                <td>admin</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h6 class="mt-4">Rôles disponibles :</h6>
                    <ul class="small">
                        <li><strong>owner</strong> : Propriétaire (contrôle total)</li>
                        <li><strong>admin</strong> : Administrateur (gère les membres)</li>
                        <li><strong>editor</strong> : Éditeur (gère les paramètres)</li>
                        <li><strong>member</strong> : Membre (accès lecture-écriture)</li>
                        <li><strong>viewer</strong> : Observateur (accès lecture seule)</li>
                    </ul>
                    
                    <hr>
                    
                    <p class="small"><strong>Note :</strong> Seuls les utilisateurs deja existants peuvent etre ajoutes. Les emails inconnus seront ignores avec un message d'erreur.</p>
                </div>
            </div>
        </div>
    </div>
</div>

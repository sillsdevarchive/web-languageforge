using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using JsonApiDotNetCore.Internal;
using JsonApiDotNetCore.Internal.Query;
using JsonApiDotNetCore.Models;
using Microsoft.AspNetCore.Http;
using NSubstitute;
using NUnit.Framework;
using SIL.XForge.DataAccess;
using SIL.XForge.Models;
using SIL.XForge.Scripture.Models;
using SIL.XForge.Services;

namespace SIL.XForge.Scripture.Services
{
    [TestFixture]
    public class SFProjectUserServiceTests
    {
        [Test]
        public async Task CreateAsync_UserRoleSameUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user02", SystemRoles.User);
            env.ParatextService.GetProjectRoleAsync(Arg.Any<UserEntity>(), "pt01")
                .Returns(Task.FromResult(SFProjectRoles.Administrator));

            Assert.That(env.ContainsProjectUser("projectusernew"), Is.False);

            var projectUser = new SFProjectUserResource
            {
                Id = "projectusernew",
                ProjectRef = "project01",
                Project = new SFProjectResource { Id = "project01" },
                UserRef = "user02",
                User = new SFUserResource { Id = "user02" }
            };
            SFProjectUserResource newProjectUser = await env.Service.CreateAsync(projectUser);

            Assert.That(newProjectUser, Is.Not.Null);
            Assert.That(newProjectUser.Id, Is.EqualTo("projectusernew"));
            Assert.That(newProjectUser.ProjectRef, Is.EqualTo("project01"));
            Assert.That(newProjectUser.UserRef, Is.EqualTo("user02"));
            Assert.That(env.ContainsProjectUser("projectusernew"), Is.True);
        }

        [Test]
        public void CreateAsync_UserRoleDifferentUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user02", SystemRoles.User);
            env.ParatextService.GetProjectRoleAsync(Arg.Any<UserEntity>(), "pt01")
                .Returns(Task.FromResult(SFProjectRoles.Administrator));

            Assert.That(env.ContainsProjectUser("projectusernew"), Is.False);

            var projectUser = new SFProjectUserResource
            {
                Id = "projectusernew",
                ProjectRef = "project01",
                Project = new SFProjectResource { Id = "project01" },
                UserRef = "user03",
                User = new SFUserResource { Id = "user03" }
            };
            var ex = Assert.ThrowsAsync<JsonApiException>(async () =>
                {
                    await env.Service.CreateAsync(projectUser);
                });

            Assert.That(ex.GetStatusCode(), Is.EqualTo(StatusCodes.Status403Forbidden));
        }

        [Test]
        public async Task CreateAsync_SystemAdminRoleDifferentUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user02", SystemRoles.SystemAdmin);
            env.ParatextService.GetProjectRoleAsync(Arg.Any<UserEntity>(), "pt01")
                .Returns(Task.FromResult(SFProjectRoles.Administrator));

            Assert.That(env.ContainsProjectUser("projectusernew"), Is.False);

            var projectUser = new SFProjectUserResource
            {
                Id = "projectusernew",
                ProjectRef = "project01",
                Project = new SFProjectResource { Id = "project01" },
                UserRef = "user03",
                User = new SFUserResource { Id = "user03" }
            };
            SFProjectUserResource newProjectUser = await env.Service.CreateAsync(projectUser);

            Assert.That(newProjectUser, Is.Not.Null);
            Assert.That(newProjectUser.Id, Is.EqualTo("projectusernew"));
            Assert.That(newProjectUser.ProjectRef, Is.EqualTo("project01"));
            Assert.That(newProjectUser.UserRef, Is.EqualTo("user03"));
            Assert.That(env.ContainsProjectUser("projectusernew"), Is.True);
        }

        [Test]
        public void CreateAsync_NonPTAdmin()
        {
            var env = new TestEnvironment();
            env.SetUser("user02", SystemRoles.User);
            env.ParatextService.GetProjectRoleAsync(Arg.Any<UserEntity>(), "pt01")
                .Returns(Task.FromResult(SFProjectRoles.Translator));

            Assert.That(env.ContainsProjectUser("projectusernew"), Is.False);

            var projectUser = new SFProjectUserResource
            {
                Id = "projectusernew",
                ProjectRef = "project01",
                Project = new SFProjectResource { Id = "project01" },
                UserRef = "user02",
                User = new SFUserResource { Id = "user02" }
            };
            var ex = Assert.ThrowsAsync<JsonApiException>(async () =>
                {
                    await env.Service.CreateAsync(projectUser);
                });

            Assert.That(ex.GetStatusCode(), Is.EqualTo(StatusCodes.Status403Forbidden));
        }

        [Test]
        public async Task UpdateAsync_UserRoleSameUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user01", SystemRoles.User);
            var newConfig = new TranslateProjectUserConfig { ConfidenceThreshold = 0.5 };
            env.JsonApiContext.AttributesToUpdate.Returns(new Dictionary<AttrAttribute, object>
                {
                    { env.GetAttribute("translate-config"), newConfig }
                });
            env.JsonApiContext.RelationshipsToUpdate.Returns(new Dictionary<RelationshipAttribute, object>());

            var projectUser = new SFProjectUserResource
            {
                Id = "projectuser01",
                TranslateConfig = newConfig
            };
            SFProjectUserResource updatedProjectUser = await env.Service.UpdateAsync(projectUser.Id, projectUser);

            Assert.That(updatedProjectUser, Is.Not.Null);
            Assert.That(updatedProjectUser.TranslateConfig.ConfidenceThreshold, Is.EqualTo(0.5));
        }

        [Test]
        public void UpdateAsync_UserRoleDifferentUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user02", SystemRoles.User);
            var newConfig = new TranslateProjectUserConfig { ConfidenceThreshold = 0.5 };
            env.JsonApiContext.AttributesToUpdate.Returns(new Dictionary<AttrAttribute, object>
                {
                    { env.GetAttribute("translate-config"), newConfig }
                });
            env.JsonApiContext.RelationshipsToUpdate.Returns(new Dictionary<RelationshipAttribute, object>());

            var projectUser = new SFProjectUserResource
            {
                Id = "projectuser01",
                TranslateConfig = newConfig
            };
            var ex = Assert.ThrowsAsync<JsonApiException>(async () =>
                {
                    await env.Service.UpdateAsync(projectUser.Id, projectUser);
                });

            Assert.That(ex.GetStatusCode(), Is.EqualTo(StatusCodes.Status403Forbidden));
        }

        [Test]
        public async Task UpdateAsync_SystemAdminRoleDifferentUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user02", SystemRoles.SystemAdmin);
            var newConfig = new TranslateProjectUserConfig { ConfidenceThreshold = 0.5 };
            env.JsonApiContext.AttributesToUpdate.Returns(new Dictionary<AttrAttribute, object>
                {
                    { env.GetAttribute("translate-config"), newConfig }
                });
            env.JsonApiContext.RelationshipsToUpdate.Returns(new Dictionary<RelationshipAttribute, object>());

            var projectUser = new SFProjectUserResource
            {
                Id = "projectuser01",
                TranslateConfig = newConfig
            };
            SFProjectUserResource updatedProjectUser = await env.Service.UpdateAsync(projectUser.Id, projectUser);

            Assert.That(updatedProjectUser, Is.Not.Null);
            Assert.That(updatedProjectUser.TranslateConfig.ConfidenceThreshold, Is.EqualTo(0.5));
        }

        [Test]
        public async Task DeleteAsync_UserRoleSameUser()
        {
            var env = new TestEnvironment();
            env.SetUser("user01", SystemRoles.User);

            Assert.That(await env.Service.DeleteAsync("projectuser01"), Is.True);

            Assert.That(env.ContainsProjectUser("projectuser01"), Is.False);
        }

        [Test]
        public void UpdateRelationshipsAsync_NotAllowed()
        {
            var env = new TestEnvironment();
            env.SetUser("user01", SystemRoles.User);

            var ex = Assert.ThrowsAsync<JsonApiException>(async () =>
                {
                    await env.Service.UpdateRelationshipsAsync("projectuser01", "project", new List<DocumentData>());
                });

            Assert.That(ex.GetStatusCode(), Is.EqualTo(StatusCodes.Status405MethodNotAllowed));
        }

        [Test]
        public async Task GetAsync()
        {
            var env = new TestEnvironment();
            env.SetUser("user01", SystemRoles.User);
            env.JsonApiContext.QuerySet.Returns(new QuerySet
                {
                    SortParameters = { new SortQuery(SortDirection.Ascending, env.GetAttribute("role")) }
                });
            env.JsonApiContext.PageManager.Returns(new PageManager());

            SFProjectUserResource[] results = (await env.Service.GetAsync()).ToArray();

            Assert.That(results.Length, Is.EqualTo(2));
            Assert.That(results[0].ProjectRef, Is.EqualTo("project03"));
            Assert.That(results[1].ProjectRef, Is.EqualTo("project01"));
        }

        [Test]
        public async Task GetRelationshipAsync()
        {
            var env = new TestEnvironment();
            env.SetUser("user01", SystemRoles.User);
            env.JsonApiContext.QuerySet.Returns(new QuerySet
                {
                    SortParameters = { new SortQuery(SortDirection.Ascending, env.GetAttribute("role")) }
                });
            env.JsonApiContext.PageManager.Returns(new PageManager());

            var project = (SFProjectResource) await env.Service.GetRelationshipAsync("projectuser01", "project");

            Assert.That(project, Is.Not.Null);
            Assert.That(project.Id, Is.EqualTo("project01"));
        }

        class TestEnvironment : ResourceServiceTestEnvironmentBase<SFProjectUserResource, SFProjectEntity>
        {
            public TestEnvironment()
                : base("project-users")
            {
                Users = new MemoryRepository<UserEntity>(new[]
                    {
                        new UserEntity { Id = "user01", Username = "user01" },
                        new UserEntity { Id = "user02", Username = "user02" },
                        new UserEntity { Id = "user03", Username = "user03" }
                    });
                ParatextService = Substitute.For<IParatextService>();
                Service = new SFProjectUserService(JsonApiContext, Mapper, UserAccessor, Entities, Users,
                    ParatextService)
                {
                    ProjectMapper = new SFProjectService(JsonApiContext, Mapper, UserAccessor, Entities),
                    UserMapper = new SFUserService(JsonApiContext, Mapper, UserAccessor, Users)
                };
            }

            public MemoryRepository<UserEntity> Users { get; }
            public IParatextService ParatextService { get; }
            public SFProjectUserService Service { get; }

            public bool ContainsProjectUser(string id)
            {
                return Entities.Query().SelectMany(p => p.Users).Any(u => u.Id == id);
            }

            protected override IEnumerable<SFProjectEntity> GetInitialData()
            {
                return new[]
                {
                    new SFProjectEntity
                    {
                        Id = "project01",
                        ProjectName = "project01",
                        ParatextId = "pt01",
                        Users =
                        {
                            new SFProjectUserEntity
                            {
                                Id = "projectuser01",
                                UserRef = "user01",
                                Role = SFProjectRoles.Translator,
                                TranslateConfig = new TranslateProjectUserConfig { ConfidenceThreshold = 0.2 }
                            }
                        }
                    },
                    new SFProjectEntity
                    {
                        Id = "project02",
                        ProjectName = "project02",
                        Users =
                        {
                            new SFProjectUserEntity
                            {
                                Id = "projectuser02",
                                UserRef = "user02",
                                Role = SFProjectRoles.Administrator
                            }
                        }
                    },
                    new SFProjectEntity
                    {
                        Id = "project03",
                        ProjectName = "project03",
                        Users =
                        {
                            new SFProjectUserEntity
                            {
                                Id = "projectuser03",
                                UserRef = "user01",
                                Role = SFProjectRoles.Administrator
                            }
                        }
                    }
                };
            }
        }
    }
}